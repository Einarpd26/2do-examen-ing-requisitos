<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\HorarioAtencion;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * CU-09: reglas para registrar una cita. Los horarios disponibles salen de una cuadrícula fija:
 * dentro de cada bloque de atención del médico, desde su hora inicial y de a la duración de la
 * especialidad. Se quitan los horarios ocupados por el médico o por el paciente, los que ya
 * pasaron y los que terminarían después del bloque. Al guardar se vuelve a comprobar todo
 * dentro de una transacción y las restricciones de la base de datos son la última defensa.
 */
class CitaService
{
    /** Nombre de la restricción de exclusión por paciente (database/sql/004). */
    private const RESTRICCION_PACIENTE = 'citas_paciente_sin_superposicion';

    private const LIBRE = 'libre';

    private const YA_PASO = 'ya_paso';

    private const MEDICO_OCUPADO = 'medico_ocupado';

    private const PACIENTE_OCUPADO = 'paciente_ocupado';

    public function __construct(private readonly BitacoraService $bitacora) {}

    /** Momento actual en la zona horaria de la aplicación (America/La_Paz). */
    public function ahora(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'));
    }

    /** Primer día en que se puede reservar (hoy). */
    public function primeraFecha(): CarbonImmutable
    {
        return $this->ahora()->startOfDay();
    }

    /** Último día en que se puede reservar (RN06 y límite de anticipación). */
    public function ultimaFecha(): CarbonImmutable
    {
        return $this->primeraFecha()->addDays((int) config('clinica.citas.dias_anticipacion_max'));
    }

    /**
     * Horarios libres del médico ese día para ese paciente, en orden.
     *
     * @return list<array{hora_inicio: string, hora_fin: string, sucursal_id: int}>
     */
    public function horariosDisponibles(Paciente $paciente, Medico $medico, Especialidad $especialidad, string $fecha): array
    {
        $dia = $this->exigirFecha($fecha);
        $this->exigirPartes($paciente, $medico, $especialidad);

        return collect($this->espacios($paciente, $medico, $especialidad, $dia))
            ->where('estado', self::LIBRE)
            ->map(fn (array $espacio) => [
                'hora_inicio' => $espacio['hora_inicio'],
                'hora_fin' => $espacio['hora_fin'],
                'sucursal_id' => $espacio['sucursal_id'],
            ])
            ->values()
            ->all();
    }

    /**
     * Registra la cita. La hora final y la sucursal las calcula el servidor; nunca vienen del formulario.
     *
     * @param  array{fecha: string, hora_inicio: string, modalidad?: string|null, motivo?: string|null}  $datos
     */
    public function registrar(Paciente $paciente, Medico $medico, Especialidad $especialidad, array $datos, Usuario $actor): Cita
    {
        return $this->conReglasDeLaBase(function () use ($paciente, $medico, $especialidad, $datos, $actor) {
            return DB::transaction(function () use ($paciente, $medico, $especialidad, $datos, $actor) {
                // Se vuelve a leer el estado actual: pudo cambiar desde que se mostró el formulario.
                $paciente->refresh();
                $medico->refresh();
                $especialidad->refresh();

                $dia = $this->exigirFecha($datos['fecha']);
                $this->exigirPartes($paciente, $medico, $especialidad);

                $espacio = collect($this->espacios($paciente, $medico, $especialidad, $dia))
                    ->firstWhere('hora_inicio', $datos['hora_inicio']);

                $this->exigirEspacioLibre($espacio);

                $cita = Cita::create([
                    'paciente_id' => $paciente->id,
                    'medico_id' => $medico->id,
                    'especialidad_id' => $especialidad->id,
                    'sucursal_id' => $espacio['sucursal_id'],
                    'fecha' => $dia->toDateString(),
                    'hora_inicio' => $espacio['hora_inicio'],
                    'hora_fin' => $espacio['hora_fin'],
                    'modalidad' => $datos['modalidad'] ?? 'PRESENCIAL',
                    'motivo' => $datos['motivo'] ?? null,
                ])->refresh();

                $this->bitacora->registrar(
                    'cita_creada', $actor, 'citas', $cita->id,
                    "{$cita->codigo}: {$paciente->codigo} con {$medico->codigo}, {$dia->format('d/m/Y')} {$cita->desde()}–{$cita->hasta()}"
                );

                return $cita;
            });
        });
    }

    /**
     * CU-11: cancela una cita. Solo se cancelan las programadas o confirmadas (las atendidas y las
     * inasistencias conservan su registro, RN10). El Paciente debe hacerlo con al menos 24 horas de
     * anticipación (RN15); la Recepcionista no tiene esa restricción. La cita no se borra: pasa a
     * CANCELADA con su motivo, deja de ocupar horario (RN09) y queda en el historial.
     */
    public function cancelar(Cita $cita, string $motivo, Usuario $actor, bool $comoPaciente): Cita
    {
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new ReglaDeNegocioException('Indique el motivo de la cancelación.');
        }

        return DB::transaction(function () use ($cita, $motivo, $actor, $comoPaciente) {
            // Se bloquea la fila y se lee de nuevo: otra solicitud pudo cancelarla o atenderla antes.
            $actual = Cita::query()->lockForUpdate()->findOrFail($cita->id);

            if ($actual->estado === 'CANCELADA') {
                throw new ReglaDeNegocioException('La cita ya está cancelada.');
            }

            if (! in_array($actual->estado, ['PROGRAMADA', 'CONFIRMADA'], true)) {
                throw new ReglaDeNegocioException('Solo se pueden cancelar las citas programadas o confirmadas.');
            }

            $inicio = CarbonImmutable::parse($actual->fecha->toDateString().' '.$actual->hora_inicio, config('app.timezone'));
            $ahora = $this->ahora();

            if ($inicio->lte($ahora)) {
                throw new ReglaDeNegocioException('La cita ya comenzó o ya pasó: no se puede cancelar.');
            }

            $horas = (int) config('clinica.citas.horas_min_cancelacion_paciente');

            if ($comoPaciente && $inicio->lt($ahora->addHours($horas))) {
                throw new ReglaDeNegocioException(
                    "Solo puede cancelar su cita con al menos {$horas} horas de anticipación. Comuníquese con la clínica."
                );
            }

            $actual->update(['estado' => 'CANCELADA', 'motivo_cancelacion' => $motivo]);

            $this->bitacora->registrar(
                'cita_cancelada', $actor, 'citas', $actual->id,
                "{$actual->codigo}: cancelada ({$actual->fecha->format('d/m/Y')} {$actual->desde()}–{$actual->hasta()}). Motivo: {$motivo}"
            );

            return $actual->refresh();
        });
    }

    /** La fecha debe existir, no ser pasada y no superar el límite de anticipación. */
    private function exigirFecha(string $fecha): CarbonImmutable
    {
        try {
            $dia = CarbonImmutable::createFromFormat('!Y-m-d', $fecha, config('app.timezone'));
        } catch (\Throwable) {
            $dia = null;
        }

        if ($dia === null || $dia->format('Y-m-d') !== $fecha) {
            throw new ReglaDeNegocioException('La fecha elegida no es válida.');
        }

        if ($dia->lt($this->primeraFecha())) {
            throw new ReglaDeNegocioException('No se pueden registrar citas en fechas pasadas.');
        }

        if ($dia->gt($this->ultimaFecha())) {
            throw new ReglaDeNegocioException(
                'Solo se pueden registrar citas con hasta '.config('clinica.citas.dias_anticipacion_max')
                ." días de anticipación (hasta el {$this->ultimaFecha()->format('d/m/Y')})."
            );
        }

        return $dia;
    }

    /** RN03, RN04 y RN16: las partes existen y están activas, el médico ejerce la especialidad y no es el paciente. */
    private function exigirPartes(Paciente $paciente, Medico $medico, Especialidad $especialidad): void
    {
        if (! $paciente->estado) {
            throw new ReglaDeNegocioException('El paciente está desactivado: no se le pueden registrar citas.');
        }

        if (! $medico->estado) {
            throw new ReglaDeNegocioException('El médico está desactivado: no se le pueden registrar citas.');
        }

        if (! $especialidad->estado) {
            throw new ReglaDeNegocioException('La especialidad está desactivada: no se pueden registrar citas en ella.');
        }

        if (! $medico->especialidades()->whereKey($especialidad->id)->exists()) {
            throw new ReglaDeNegocioException('El médico no ejerce la especialidad elegida.');
        }

        if ($medico->persona_id === $paciente->persona_id) {
            throw new ReglaDeNegocioException('Un médico no puede agendar una cita consigo mismo como paciente.');
        }
    }

    /** Traduce el estado del horario pedido en el mensaje que corresponde. */
    private function exigirEspacioLibre(?array $espacio): void
    {
        $motivo = match ($espacio['estado'] ?? null) {
            null => 'La hora elegida no corresponde a un horario de atención del médico ese día.',
            self::YA_PASO => 'Esa hora ya pasó: elija un horario posterior.',
            self::MEDICO_OCUPADO => 'El médico ya tiene una cita en ese horario: elija otra hora.',
            self::PACIENTE_OCUPADO => 'El paciente ya tiene otra cita que se superpone con ese horario: elija otra hora.',
            default => null,
        };

        if ($motivo !== null) {
            throw new ReglaDeNegocioException($motivo);
        }
    }

    /**
     * Cuadrícula completa del día: cada horario con su estado (libre, ya pasó, médico ocupado
     * o paciente ocupado). Las citas canceladas no ocupan horario (RN09).
     *
     * @return list<array{hora_inicio: string, hora_fin: string, sucursal_id: int, estado: string}>
     */
    private function espacios(Paciente $paciente, Medico $medico, Especialidad $especialidad, CarbonImmutable $dia): array
    {
        $duracion = (int) $especialidad->duracion_min;
        if ($duracion <= 0) {
            return [];
        }

        $citas = Cita::query()
            ->whereDate('fecha', $dia->toDateString())
            ->where('estado', '<>', 'CANCELADA')
            ->where(fn ($consulta) => $consulta->where('medico_id', $medico->id)->orWhere('paciente_id', $paciente->id))
            ->get(['medico_id', 'paciente_id', 'hora_inicio', 'hora_fin'])
            ->map(fn (Cita $cita) => [
                'inicio' => $this->minutos($cita->hora_inicio),
                'fin' => $this->minutos($cita->hora_fin),
                'del_medico' => $cita->medico_id === $medico->id,
            ]);

        $ahora = $this->ahora();
        $minutosAhora = $dia->isSameDay($ahora) ? $ahora->hour * 60 + $ahora->minute : -1;

        $bloques = HorarioAtencion::query()
            ->where('medico_id', $medico->id)
            ->where('dia_semana', $dia->isoWeekday())
            ->orderBy('hora_inicio')
            ->get();

        $espacios = [];

        foreach ($bloques as $bloque) {
            $limite = $this->minutos($bloque->hora_fin);

            for ($inicio = $this->minutos($bloque->hora_inicio); $inicio + $duracion <= $limite; $inicio += $duracion) {
                $fin = $inicio + $duracion;
                $choques = $citas->filter(fn (array $cita) => $cita['inicio'] < $fin && $cita['fin'] > $inicio);

                $espacios[] = [
                    'hora_inicio' => $this->hora($inicio),
                    'hora_fin' => $this->hora($fin),
                    'sucursal_id' => (int) $bloque->sucursal_id,
                    'estado' => match (true) {
                        $inicio <= $minutosAhora => self::YA_PASO,
                        $choques->contains('del_medico', true) => self::MEDICO_OCUPADO,
                        $choques->isNotEmpty() => self::PACIENTE_OCUPADO,
                        default => self::LIBRE,
                    },
                ];
            }
        }

        return $espacios;
    }

    /** Minutos desde la medianoche de una hora «HH:MM» o «HH:MM:SS». */
    private function minutos(string $hora): int
    {
        return (int) substr($hora, 0, 2) * 60 + (int) substr($hora, 3, 2);
    }

    private function hora(int $minutos): string
    {
        return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
    }

    /**
     * Si otra solicitud ganó la carrera entre la validación y el guardado, la base de datos
     * rechaza la cita superpuesta (restricción de exclusión) y se informa como regla de negocio.
     */
    private function conReglasDeLaBase(\Closure $operacion): mixed
    {
        try {
            return $operacion();
        } catch (QueryException $e) {
            throw $this->reglaDeLaBase($e) ?? $e;
        }
    }

    /**
     * 23P01 es la violación de una restricción de exclusión. El nombre de la restricción que
     * trae el error distingue si chocó el paciente o el médico.
     */
    public function reglaDeLaBase(QueryException $e): ?ReglaDeNegocioException
    {
        if (($e->errorInfo[0] ?? null) !== '23P01') {
            return null;
        }

        return str_contains($e->getMessage(), '"'.self::RESTRICCION_PACIENTE.'"')
            ? new ReglaDeNegocioException('El paciente ya tiene otra cita que se superpone con ese horario: elija otra hora.')
            : new ReglaDeNegocioException('El médico ya tiene una cita en ese horario: otra solicitud lo ocupó. Elija otra hora.');
    }
}

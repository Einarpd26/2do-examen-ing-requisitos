<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Cita;
use App\Models\Medico;
use App\Models\NotaClinica;
use App\Models\Paciente;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CU-16 y CU-17: notas clínicas (RF16). Una nota por cita atendida, que escribe y edita solo el
 * médico de la cita. Al editarla se sobrescribe y se guarda quién y cuándo la modificó, sin
 * versiones. La ven el Médico, la Recepcionista y el propio Paciente, en solo lectura.
 */
class NotaClinicaService
{
    public const CAMPOS = ['motivo_consulta', 'diagnostico', 'tratamiento', 'observaciones'];

    public function __construct(private readonly BitacoraService $bitacora) {}

    /** Médico de la cuenta, o null si la cuenta no tiene ficha de médico. */
    public function medicoDe(Usuario $cuenta): ?Medico
    {
        return $cuenta->persona->medico;
    }

    /**
     * Crea o actualiza la nota de la cita. Solo el médico de la cita, y solo si está atendida.
     *
     * @param  array<string, string|null>  $datos
     */
    public function guardar(Cita $cita, array $datos, Usuario $actor): NotaClinica
    {
        return DB::transaction(function () use ($cita, $datos, $actor) {
            // Se bloquea la cita y se lee de nuevo: su estado pudo cambiar mientras se escribía.
            $actual = Cita::query()->lockForUpdate()->findOrFail($cita->id);
            $medico = $this->medicoDe($actor);

            if ($medico === null || $medico->id !== $actual->medico_id) {
                throw new ReglaDeNegocioException('Solo el médico de la cita puede escribir su nota clínica.');
            }

            if ($actual->estado !== 'ATENDIDA') {
                throw new ReglaDeNegocioException('Solo se registra la nota clínica de una cita atendida.');
            }

            $valores = collect(self::CAMPOS)
                ->mapWithKeys(fn (string $campo) => [$campo => filled($datos[$campo] ?? null) ? trim($datos[$campo]) : null])
                ->all();

            if (collect($valores)->filter()->isEmpty()) {
                throw new ReglaDeNegocioException('Complete al menos un campo de la nota clínica.');
            }

            $nota = NotaClinica::query()->where('cita_id', $actual->id)->first();
            $nueva = $nota === null;

            if ($nueva) {
                $nota = NotaClinica::create($valores + [
                    'cita_id' => $actual->id,
                    'medico_id' => $actual->medico_id,
                    'modificado_por' => $actor->id,
                ]);
            } else {
                $nota->update($valores + ['modificado_por' => $actor->id]);
            }

            $this->bitacora->registrar(
                $nueva ? 'nota_registrada' : 'nota_modificada', $actor, 'notas_clinicas', $nota->id,
                "{$actual->codigo}: nota clínica ".($nueva ? 'registrada' : 'modificada')
            );

            return $nota->refresh();
        });
    }

    /** ¿Tiene o tuvo el paciente una cita (no cancelada) con este médico? Condición para ver sus notas. */
    public function atiende(Medico $medico, Paciente $paciente): bool
    {
        return Cita::query()->where('medico_id', $medico->id)->where('paciente_id', $paciente->id)
            ->where('estado', '<>', 'CANCELADA')->exists();
    }

    /**
     * Notas del paciente, de la cita más reciente a la más antigua, con su cita, su médico y quién la modificó.
     *
     * @return Collection<int, NotaClinica>
     */
    public function de(Paciente $paciente): Collection
    {
        return NotaClinica::query()
            ->join('citas', 'citas.id', '=', 'notas_clinicas.cita_id')
            ->where('citas.paciente_id', $paciente->id)
            ->select('notas_clinicas.*')
            ->with('cita.especialidad', 'medico.persona', 'modificador.persona')
            ->orderByDesc('citas.fecha')->orderByDesc('citas.hora_inicio')
            ->get();
    }
}

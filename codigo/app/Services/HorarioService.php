<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Cita;
use App\Models\HorarioAtencion;
use App\Models\Medico;
use App\Models\Sucursal;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CU-07: reglas de la gestión de horarios de atención. Cada operación escribe en la bitácora.
 * Un horario no se puede modificar ni eliminar si dejaría fuera de la atención a una cita
 * ya agendada; y no se toca el horario de un médico desactivado.
 */
class HorarioService
{
    private const CAMPOS = ['dia_semana', 'hora_inicio', 'hora_fin', 'consultorio'];

    public function __construct(private readonly BitacoraService $bitacora) {}

    /** @param  array<string, mixed>  $datos */
    public function registrar(Medico $medico, array $datos, Usuario $actor): HorarioAtencion
    {
        $this->exigirMedicoActivo($medico);

        return $this->conReglasDeLaBase(function () use ($medico, $datos, $actor) {
            return DB::transaction(function () use ($medico, $datos, $actor) {
                $horario = HorarioAtencion::create(
                    ['medico_id' => $medico->id, 'sucursal_id' => Sucursal::predeterminada()->id] + Arr::only($datos, self::CAMPOS)
                );
                $horario->refresh();

                $this->bitacora->registrar('horario_creado', $actor, 'horarios_atencion', $horario->id, $horario->descripcion());

                return $horario;
            });
        });
    }

    /** @param  array<string, mixed>  $datos */
    public function actualizar(HorarioAtencion $horario, array $datos, Usuario $actor): void
    {
        $medico = $horario->medico;
        $this->exigirMedicoActivo($medico);

        $this->conReglasDeLaBase(function () use ($horario, $medico, $datos, $actor) {
            DB::transaction(function () use ($horario, $medico, $datos, $actor) {
                $antes = $horario->descripcion();

                $this->sinDejarCitasFueraDelHorario($medico, 'modificar', function () use ($horario, $datos) {
                    $horario->update(Arr::only($datos, self::CAMPOS));
                });

                $this->bitacora->registrar(
                    'horario_modificado', $actor, 'horarios_atencion', $horario->id, "{$antes} → {$horario->refresh()->descripcion()}"
                );
            });
        });
    }

    public function eliminar(HorarioAtencion $horario, Usuario $actor): void
    {
        $medico = $horario->medico;
        $this->exigirMedicoActivo($medico);

        DB::transaction(function () use ($horario, $medico, $actor) {
            $descripcion = $horario->descripcion();

            $this->sinDejarCitasFueraDelHorario($medico, 'eliminar', fn () => $horario->delete());

            $this->bitacora->registrar('horario_eliminado', $actor, 'horarios_atencion', $horario->id, $descripcion);
        });
    }

    private function exigirMedicoActivo(Medico $medico): void
    {
        if (! $medico->estado) {
            throw new ReglaDeNegocioException('El médico está desactivado: reactívelo para modificar sus horarios.');
        }
    }

    /**
     * Aplica el cambio y comprueba que no queden citas futuras fuera del horario de atención.
     * Si alguna cita que estaba cubierta deja de estarlo, se revierte todo (la excepción
     * deshace la transacción). Las citas que ya estaban fuera antes del cambio no cuentan.
     */
    private function sinDejarCitasFueraDelHorario(Medico $medico, string $accion, \Closure $cambio): void
    {
        $antes = $this->citasFueraDelHorario($medico);

        $cambio();

        $nuevas = $this->citasFueraDelHorario($medico)->diff($antes);

        if ($nuevas->isNotEmpty()) {
            $codigos = Cita::whereIn('id', $nuevas)->orderBy('fecha')->orderBy('hora_inicio')->limit(3)->get()
                ->map(fn (Cita $cita) => "{$cita->codigo} ({$cita->fecha->format('d/m/Y')} {$cita->hora_inicio})")
                ->implode(', ');
            $resto = $nuevas->count() > 3 ? ' y otras' : '';

            throw new ReglaDeNegocioException(
                "No se puede {$accion} este horario: quedarían citas agendadas fuera de la atención ({$codigos}{$resto}). "
                .'Reprográmelas o cancélelas antes.'
            );
        }
    }

    /**
     * Citas futuras, programadas o confirmadas, del médico que no caen dentro de ningún
     * bloque de su horario.
     *
     * @return Collection<int, int>
     */
    private function citasFueraDelHorario(Medico $medico): Collection
    {
        return DB::table('citas as c')
            ->where('c.medico_id', $medico->id)
            ->whereIn('c.estado', ['PROGRAMADA', 'CONFIRMADA'])
            ->whereRaw('c.fecha >= CURRENT_DATE')
            ->whereNotExists(function ($horario) {
                $horario->selectRaw('1')
                    ->from('horarios_atencion as h')
                    ->whereColumn('h.medico_id', 'c.medico_id')
                    ->whereRaw('h.dia_semana = EXTRACT(ISODOW FROM c.fecha)')
                    ->whereColumn('h.hora_inicio', '<=', 'c.hora_inicio')
                    ->whereColumn('h.hora_fin', '>=', 'c.hora_fin');
            })
            ->pluck('c.id')
            ->map(fn ($id) => (int) $id);
    }

    /**
     * Si otra solicitud ganó la carrera entre la validación y el guardado, la base de datos
     * rechaza el bloque superpuesto (restricción de exclusión) y se informa como regla de negocio.
     */
    private function conReglasDeLaBase(\Closure $operacion): mixed
    {
        try {
            return $operacion();
        } catch (QueryException $e) {
            // 23P01: violación de la restricción de exclusión (bloques superpuestos del mismo médico).
            if (($e->errorInfo[0] ?? null) === '23P01') {
                throw new ReglaDeNegocioException('Este horario se superpone con otro del mismo médico el mismo día.');
            }

            throw $e;
        }
    }
}

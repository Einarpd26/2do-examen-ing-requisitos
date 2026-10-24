<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\Persona;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * CU-05: reglas de la gestión de médicos. Un médico nunca se elimina: se desactiva (RN11).
 * Cada operación escribe en la bitácora.
 */
class MedicoService
{
    private const CAMPOS_PERSONA = [
        'ci', 'primer_nombre', 'segundo_nombre', 'apellido_paterno', 'apellido_materno', 'telefono', 'correo',
    ];

    public function __construct(private readonly BitacoraService $bitacora) {}

    /**
     * Registra un médico. Si el CI ya existe como persona sin ficha de médico (por ejemplo, un
     * paciente), se reutiliza esa persona y no se tocan sus datos personales.
     *
     * @param  array<string, mixed>  $datos
     * @return array{0: Medico, 1: bool} el médico y si se reutilizó una persona existente
     */
    public function registrar(array $datos, Usuario $actor): array
    {
        return $this->conReglasDeLaBase(function () use ($datos, $actor) {
            return DB::transaction(function () use ($datos, $actor) {
                $persona = Persona::where('ci', $datos['ci'])->first();
                $reutilizada = $persona !== null;
                $persona ??= Persona::create(Arr::only($datos, self::CAMPOS_PERSONA));

                if ($persona->medico()->exists()) {
                    throw new ReglaDeNegocioException('Ya existe un médico registrado con este CI.');
                }

                $medico = Medico::create([
                    'persona_id' => $persona->id,
                    'nro_registro_profesional' => $datos['nro_registro_profesional'],
                ])->refresh();

                $medico->especialidades()->sync($datos['especialidades']);
                $this->bitacora->registrar('medico_creado', $actor, 'medicos', $medico->id);

                return [$medico, $reutilizada];
            });
        });
    }

    /** @param  array<string, mixed>  $datos */
    public function actualizar(Medico $medico, array $datos, Usuario $actor): void
    {
        $this->conReglasDeLaBase(function () use ($medico, $datos, $actor) {
            DB::transaction(function () use ($medico, $datos, $actor) {
                $this->comprobarQueNoSeQuitenEspecialidadesConCitas($medico, $datos['especialidades']);

                $medico->persona->update(Arr::only($datos, self::CAMPOS_PERSONA));
                $medico->update(['nro_registro_profesional' => $datos['nro_registro_profesional']]);
                $medico->especialidades()->sync($datos['especialidades']);

                $this->bitacora->registrar('medico_modificado', $actor, 'medicos', $medico->id);
            });
        });
    }

    /**
     * Desactiva o reactiva al médico sin borrar nada (RN04, RN11). Un médico inactivo no recibe
     * citas nuevas y conserva las históricas. Su cuenta de médico, si la tiene, sigue el mismo
     * estado: un médico dado de baja no puede iniciar sesión.
     */
    public function cambiarEstado(Medico $medico, bool $activo, Usuario $actor): void
    {
        DB::transaction(function () use ($medico, $activo, $actor) {
            $medico->update(['estado' => $activo]);

            $cuenta = $medico->persona->usuario;
            if ($cuenta !== null && $cuenta->rol->codigo === 'MEDICO') {
                $cuenta->update(['estado' => $activo]);
            }

            $this->bitacora->registrar(
                $activo ? 'medico_reactivado' : 'medico_desactivado', $actor, 'medicos', $medico->id
            );
        });
    }

    /**
     * Una especialidad con citas registradas no se le puede quitar al médico: se perdería el
     * vínculo de esas citas. La base también lo impide, pero así el mensaje dice cuál es.
     *
     * @param  array<int, int|string>  $nuevas
     */
    private function comprobarQueNoSeQuitenEspecialidadesConCitas(Medico $medico, array $nuevas): void
    {
        $quitadas = $medico->especialidades()->pluck('especialidades.id')
            ->diff(array_map('intval', $nuevas));

        if ($quitadas->isEmpty()) {
            return;
        }

        $conCitas = Cita::where('medico_id', $medico->id)
            ->whereIn('especialidad_id', $quitadas)
            ->distinct()
            ->pluck('especialidad_id');

        if ($conCitas->isNotEmpty()) {
            $nombres = Especialidad::whereIn('id', $conCitas)->orderBy('nombre')->pluck('nombre')->implode(', ');

            throw new ReglaDeNegocioException(
                "No se puede quitar la especialidad {$nombres}: el médico tiene citas registradas con ella."
            );
        }
    }

    /**
     * Si otra solicitud ganó la carrera entre la validación y el guardado, la base de datos lo
     * rechaza y se informa como regla de negocio en lugar de un error técnico.
     */
    private function conReglasDeLaBase(\Closure $operacion): mixed
    {
        try {
            return $operacion();
        } catch (UniqueConstraintViolationException) {
            throw new ReglaDeNegocioException('El registro profesional o el correo ya pertenecen a otra persona.');
        } catch (QueryException $e) {
            // 23503: violación de clave foránea (por ejemplo, quitar una especialidad con citas).
            if (($e->errorInfo[0] ?? null) === '23503') {
                throw new ReglaDeNegocioException('No se puede quitar una especialidad con la que el médico tiene citas registradas.');
            }

            throw $e;
        }
    }
}

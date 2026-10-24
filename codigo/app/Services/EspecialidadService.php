<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Especialidad;
use App\Models\Usuario;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * CU-06: reglas de la gestión de especialidades. Una especialidad nunca se elimina: se
 * desactiva, porque los médicos y las citas la referencian. Cada operación queda en la bitácora.
 */
class EspecialidadService
{
    public const MENSAJE_NOMBRE_REPETIDO = 'Ya existe una especialidad con ese nombre.';

    private const CAMPOS = ['nombre', 'descripcion', 'duracion_min'];

    public function __construct(private readonly BitacoraService $bitacora) {}

    /** @param  array<string, mixed>  $datos */
    public function registrar(array $datos, Usuario $actor): Especialidad
    {
        return $this->sinNombreRepetido(function () use ($datos, $actor) {
            return DB::transaction(function () use ($datos, $actor) {
                $especialidad = Especialidad::create(Arr::only($datos, self::CAMPOS))->refresh();
                $this->bitacora->registrar('especialidad_creada', $actor, 'especialidades', $especialidad->id);

                return $especialidad;
            });
        });
    }

    /**
     * Cambiar la duración solo afecta a las citas nuevas: las ya agendadas guardan su hora de
     * inicio y de fin.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Especialidad $especialidad, array $datos, Usuario $actor): void
    {
        $this->sinNombreRepetido(function () use ($especialidad, $datos, $actor) {
            DB::transaction(function () use ($especialidad, $datos, $actor) {
                $especialidad->update(Arr::only($datos, self::CAMPOS));
                $this->bitacora->registrar('especialidad_modificada', $actor, 'especialidades', $especialidad->id);
            });
        });
    }

    /** Una especialidad inactiva no se ofrece para asignarla a médicos ni para nuevas citas. */
    public function cambiarEstado(Especialidad $especialidad, bool $activa, Usuario $actor): void
    {
        DB::transaction(function () use ($especialidad, $activa, $actor) {
            $especialidad->update(['estado' => $activa]);
            $this->bitacora->registrar(
                $activa ? 'especialidad_reactivada' : 'especialidad_desactivada', $actor, 'especialidades', $especialidad->id
            );
        });
    }

    /**
     * Si otra solicitud guardó el mismo nombre entre la validación y el guardado, la base de
     * datos lo rechaza (índice único sobre el nombre normalizado) y se informa como regla de negocio.
     */
    private function sinNombreRepetido(\Closure $operacion): mixed
    {
        try {
            return $operacion();
        } catch (UniqueConstraintViolationException) {
            throw new ReglaDeNegocioException(self::MENSAJE_NOMBRE_REPETIDO);
        }
    }
}

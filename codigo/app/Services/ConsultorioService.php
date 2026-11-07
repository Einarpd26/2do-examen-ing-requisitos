<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Consultorio;
use App\Models\Sucursal;
use App\Models\Usuario;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * CU-20: reglas de la gestión de consultorios (ADR-002). Un consultorio nunca se elimina: se
 * desactiva, y solo si ningún bloque de atención lo usa. Cada operación queda en la bitácora.
 */
class ConsultorioService
{
    public const MENSAJE_NOMBRE_REPETIDO = 'Ya existe un consultorio con ese nombre.';

    public function __construct(private readonly BitacoraService $bitacora) {}

    /**
     * La sucursal se asigna sola: hoy hay una única sucursal activa.
     *
     * @param  array<string, mixed>  $datos
     */
    public function registrar(array $datos, Usuario $actor): Consultorio
    {
        return $this->sinNombreRepetido(function () use ($datos, $actor) {
            return DB::transaction(function () use ($datos, $actor) {
                $consultorio = Consultorio::create([
                    'sucursal_id' => Sucursal::predeterminada()->id,
                    'nombre' => $datos['nombre'],
                ])->refresh();

                $this->bitacora->registrar('consultorio_creado', $actor, 'consultorios', $consultorio->id, $consultorio->nombre);

                return $consultorio;
            });
        });
    }

    /**
     * Renombrar no afecta a los bloques: lo referencian por su identificador.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Consultorio $consultorio, array $datos, Usuario $actor): void
    {
        $this->sinNombreRepetido(function () use ($consultorio, $datos, $actor) {
            DB::transaction(function () use ($consultorio, $datos, $actor) {
                $antes = $consultorio->nombre;
                $consultorio->update(['nombre' => $datos['nombre']]);

                $this->bitacora->registrar(
                    'consultorio_modificado', $actor, 'consultorios', $consultorio->id, "{$antes} → {$consultorio->nombre}"
                );
            });
        });
    }

    /**
     * Un consultorio inactivo no se ofrece en los horarios. No se puede desactivar mientras algún
     * bloque lo use, para que ningún horario quede con un consultorio dado de baja (ADR-002, decisión 4).
     */
    public function cambiarEstado(Consultorio $consultorio, bool $activo, Usuario $actor): void
    {
        DB::transaction(function () use ($consultorio, $activo, $actor) {
            // Bloquea la fila: un bloque nuevo no puede tomar el consultorio mientras se desactiva.
            $consultorio = Consultorio::query()->lockForUpdate()->findOrFail($consultorio->id);

            if (! $activo) {
                $bloques = $consultorio->horarios()->count();

                if ($bloques > 0) {
                    throw new ReglaDeNegocioException(
                        "No se puede desactivar el {$consultorio->nombre}: lo usan {$bloques} "
                        .($bloques === 1 ? 'bloque' : 'bloques').' de atención. Asígneles otro consultorio antes.'
                    );
                }
            }

            $consultorio->update(['estado' => $activo]);
            $this->bitacora->registrar(
                $activo ? 'consultorio_reactivado' : 'consultorio_desactivado', $actor, 'consultorios', $consultorio->id
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

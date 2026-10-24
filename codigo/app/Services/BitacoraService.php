<?php

namespace App\Services;

use App\Models\Bitacora;
use App\Models\Usuario;

/**
 * Registro de auditoría (RNF13). Solo escribe: la base de datos no permite modificar ni
 * borrar la bitácora.
 */
class BitacoraService
{
    public function registrar(
        string $accion,
        ?Usuario $usuario = null,
        ?string $entidad = null,
        ?int $entidadId = null,
    ): void {
        Bitacora::create([
            'usuario_id' => $usuario?->id,
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
        ]);
    }
}

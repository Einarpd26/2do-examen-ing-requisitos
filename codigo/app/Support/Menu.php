<?php

namespace App\Support;

use App\Models\Usuario;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Menú del usuario según los permisos de su rol (RF01). Cada opción está en config/menu.php.
 * Una opción cuya pantalla todavía no existe se muestra deshabilitada.
 */
class Menu
{
    /**
     * @return array<int, array{etiqueta: string, url: string|null}>
     */
    public static function para(Usuario $cuenta): array
    {
        $opciones = [];

        foreach (config('menu.opciones') as $opcion) {
            if (! Gate::forUser($cuenta)->allows($opcion['permiso'])) {
                continue;
            }

            // Algunas opciones comparten el permiso con otro rol pero solo sirven a uno (p. ej. «Mi historial»).
            if (isset($opcion['rol']) && $cuenta->rol->codigo !== $opcion['rol']) {
                continue;
            }

            $opciones[] = [
                'etiqueta' => $opcion['etiqueta'],
                'url' => Route::has($opcion['ruta']) ? route($opcion['ruta']) : null,
            ];
        }

        return $opciones;
    }
}

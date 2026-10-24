<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Listados con baja lógica: pestañas Activos / Inactivos / Todos con contador y búsqueda.
 * Sin texto de búsqueda se ven los activos; con texto se busca en todos, para poder encontrar
 * también a quien está dado de baja (por ejemplo, para reactivarlo).
 */
trait ListadoConPestanas
{
    /** @return array{activas: string, inactivas: string, todas: string} clave => etiqueta de la pestaña */
    abstract protected function etiquetasDePestanas(): array;

    /** Una pestaña desconocida cae en la de activas. */
    protected function vista(mixed $valor): string
    {
        return array_key_exists((string) $valor, $this->etiquetasDePestanas()) ? (string) $valor : 'activas';
    }

    protected function vistaDelListado(Request $request, string $texto): string
    {
        return $request->has('estado')
            ? $this->vista($request->query('estado'))
            : ($texto !== '' ? 'todas' : 'activas');
    }

    /**
     * Parámetros de la URL del listado. Con búsqueda el estado va siempre, porque sin él se
     * buscaría en todos.
     *
     * @return array<string, string>
     */
    protected function parametrosDelListado(string $vista, string $texto): array
    {
        return array_filter([
            'q' => $texto,
            'estado' => ($vista !== 'activas' || $texto !== '') ? $vista : null,
        ]);
    }

    /**
     * @param  array{activas: int, inactivas: int, todas: int}  $cuentas
     * @return array<string, array{etiqueta: string, cuenta: int, url: string}>
     */
    protected function pestanas(string $ruta, array $cuentas, string $texto): array
    {
        $pestanas = [];

        foreach ($this->etiquetasDePestanas() as $clave => $etiqueta) {
            $pestanas[$clave] = [
                'etiqueta' => $etiqueta,
                'cuenta' => $cuentas[$clave],
                'url' => route($ruta, $this->parametrosDelListado($clave, $texto)),
            ];
        }

        return $pestanas;
    }
}

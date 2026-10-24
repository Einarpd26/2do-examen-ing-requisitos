<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Código legible de una entidad (PAC-000042). En la base de datos solo se guarda el id;
 * el formato se arma al mostrarlo. El modelo debe definir la constante PREFIJO_CODIGO.
 */
trait TieneCodigoFormateado
{
    protected function codigo(): Attribute
    {
        return Attribute::get(
            fn () => static::PREFIJO_CODIGO.'-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT)
        );
    }

    /**
     * Convierte "PAC-000042", "pac-42" o "42" en el id 42.
     * Devuelve null si el texto no tiene forma de código.
     */
    public static function idDesdeCodigo(string $texto): ?int
    {
        $patron = '/^(?:'.static::PREFIJO_CODIGO.'-)?0*(\d+)$/i';

        return preg_match($patron, trim($texto), $coincidencia) === 1 ? (int) $coincidencia[1] : null;
    }
}

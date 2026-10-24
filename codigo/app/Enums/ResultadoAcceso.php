<?php

namespace App\Enums;

/**
 * Resultado de un intento de inicio de sesión (CU-01).
 */
enum ResultadoAcceso
{
    case Correcto;
    case CredencialesIncorrectas;
    case Bloqueada;
    case Inactiva;
    case TemporalVencida;

    /**
     * Mensaje para el usuario. Los datos incorrectos usan un mensaje genérico, para no
     * revelar si el CI o el correo existen.
     */
    public function mensaje(?int $minutosEspera = null): string
    {
        return match ($this) {
            self::Correcto => '',
            self::CredencialesIncorrectas => 'El CI, el correo o la contraseña no son correctos.',
            self::Bloqueada => 'La cuenta está bloqueada por intentos fallidos. Vuelva a intentarlo en '
                .max(1, $minutosEspera ?? 1).' '.(($minutosEspera ?? 1) === 1 ? 'minuto' : 'minutos').'.',
            self::Inactiva => 'La cuenta está desactivada. Comuníquese con recepción.',
            self::TemporalVencida => 'La contraseña temporal venció. Pida una nueva en recepción: '
                .config('clinica.celular').'.',
        };
    }
}

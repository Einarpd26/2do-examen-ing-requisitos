<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Se lanza cuando una operación incumple una regla del negocio (por ejemplo, crear una
 * segunda cuenta para el mismo paciente). Su mensaje es para el usuario y se muestra tal cual.
 */
class ReglaDeNegocioException extends RuntimeException {}

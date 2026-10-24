<?php

namespace App\Services;

use App\Enums\ResultadoAcceso;
use App\Models\Usuario;

/**
 * Resultado de AutenticacionService::intentar(): qué pasó, la cuenta (si se encontró) y,
 * en un bloqueo, los minutos de espera que quedan.
 */
final readonly class IntentoAcceso
{
    public function __construct(
        public ResultadoAcceso $resultado,
        public ?Usuario $cuenta = null,
        public ?int $minutosEspera = null,
    ) {}

    public function fueCorrecto(): bool
    {
        return $this->resultado === ResultadoAcceso::Correcto;
    }

    public function mensaje(): string
    {
        return $this->resultado->mensaje($this->minutosEspera);
    }
}

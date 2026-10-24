<?php

namespace App\Http\Requests\Concerns;

/**
 * Deja los datos de una persona en la forma en que se guardan, antes de validarlos:
 * CI en mayúsculas y sin espacios, correo en minúsculas y teléfonos sin separadores.
 */
trait NormalizaDatosDePersona
{
    protected function prepareForValidation(): void
    {
        $limpio = [];

        if (is_string($this->input('ci'))) {
            $limpio['ci'] = strtoupper(preg_replace('/\s+/', '', $this->input('ci')));
        }

        if (is_string($this->input('correo'))) {
            $limpio['correo'] = strtolower(trim($this->input('correo')));
        }

        foreach (['telefono', 'emergencia_telefono'] as $campo) {
            if (is_string($this->input($campo))) {
                $limpio[$campo] = preg_replace('/[\s\-()]/', '', $this->input($campo));
            }
        }

        $this->merge($limpio);
    }
}

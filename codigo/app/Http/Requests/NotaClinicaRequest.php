<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos de una nota clínica (RF16, CU-16): cuatro textos opcionales de hasta 2000 caracteres,
 * de los cuales al menos uno debe tener contenido. El autor y la cita no se reciben del formulario.
 */
class NotaClinicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo_consulta' => ['nullable', 'string', 'max:2000'],
            'diagnostico' => ['nullable', 'string', 'max:2000'],
            'tratamiento' => ['nullable', 'string', 'max:2000'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_consulta.max' => 'El motivo de consulta no puede superar los 2000 caracteres.',
            'diagnostico.max' => 'El diagnóstico no puede superar los 2000 caracteres.',
            'tratamiento.max' => 'El tratamiento o las indicaciones no pueden superar los 2000 caracteres.',
            'observaciones.max' => 'Las observaciones no pueden superar los 2000 caracteres.',
        ];
    }

    public function after(): array
    {
        return [function ($validador) {
            if ($validador->errors()->isEmpty() && collect($this->only(['motivo_consulta', 'diagnostico', 'tratamiento', 'observaciones']))->filter()->isEmpty()) {
                $validador->errors()->add('motivo_consulta', 'Complete al menos un campo de la nota clínica.');
            }
        }];
    }
}

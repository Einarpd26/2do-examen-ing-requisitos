<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos para cancelar una cita (RF09, CU-11). Solo valida la forma: el motivo es obligatorio.
 * Las reglas del negocio (estado, 24 horas del Paciente) las aplica CitaService.
 */
class CancelarCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['motivo_cancelacion' => ['required', 'string', 'max:500']];
    }

    public function messages(): array
    {
        return [
            'motivo_cancelacion.required' => 'Indique el motivo de la cancelación.',
            'motivo_cancelacion.max' => 'El motivo no puede superar los 500 caracteres.',
        ];
    }
}

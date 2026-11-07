<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos para reprogramar una cita (RF08, CU-10): solo la nueva fecha y la nueva hora. El paciente,
 * el médico y la especialidad no se reciben; la hora final la calcula el servidor (RN08).
 */
class ReprogramarCitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.required' => 'Elija la nueva fecha de la cita.',
            'fecha.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            'hora_inicio.required' => 'Elija un horario disponible.',
            'hora_inicio.date_format' => 'El horario elegido no es válido.',
        ];
    }
}

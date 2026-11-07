<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos del formulario de cita (RF06). Solo valida la forma; las reglas del negocio las aplica
 * CitaService. La hora final no se recibe: la calcula el servidor. El paciente de una cuenta de
 * paciente se toma siempre de su sesión, así que el campo paciente_id solo se exige en citas.*.
 */
class CitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // En mis-citas.* se ignora paciente_id: el paciente es el de la cuenta (RN12).
        // bail: un número fuera de rango se rechaza aquí, sin llegar a consultarlo en la base.
        $paciente = $this->routeIs('citas.*') ? ['paciente_id' => ['bail', 'required', 'integer', 'exists:pacientes,id']] : [];

        return $paciente + [
            'especialidad_id' => ['bail', 'required', 'integer', 'exists:especialidades,id'],
            'medico_id' => ['bail', 'required', 'integer', 'exists:medicos,id'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'modalidad' => ['required', 'in:PRESENCIAL,VIRTUAL'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'paciente_id.required' => 'Elija al paciente.',
            'paciente_id.exists' => 'El paciente elegido no existe.',
            'especialidad_id.required' => 'Elija la especialidad.',
            'especialidad_id.exists' => 'La especialidad elegida no existe.',
            'medico_id.required' => 'Elija al médico.',
            'medico_id.exists' => 'El médico elegido no existe.',
            'fecha.required' => 'Elija la fecha de la cita.',
            'fecha.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            'hora_inicio.required' => 'Elija un horario disponible.',
            'hora_inicio.date_format' => 'El horario elegido no es válido.',
            'modalidad.required' => 'Elija la modalidad de la cita.',
            'modalidad.in' => 'La modalidad debe ser presencial o virtual.',
            'motivo.max' => 'El motivo no puede superar los 500 caracteres.',
        ];
    }
}

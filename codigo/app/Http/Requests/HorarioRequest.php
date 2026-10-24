<?php

namespace App\Http\Requests;

use App\Models\HorarioAtencion;
use App\Models\Medico;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos del formulario de horario de atención (RF05): día, hora inicial, hora final y
 * consultorio. Sirve para registrar (la ruta trae el médico) y para modificar (trae el horario).
 */
class HorarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var HorarioAtencion|null $horario */
        $horario = $this->route('horario');
        /** @var Medico|null $medico */
        $medico = $this->route('medico');

        return [
            'dia_semana' => ['required', 'integer', 'between:1,7'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => [
                'bail',
                'required',
                'date_format:H:i',
                'after:hora_inicio',
                $this->sinSuperposicion($horario?->medico_id ?? $medico?->id, $horario?->id),
            ],
            'consultorio' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'dia_semana.required' => 'Elija el día de la semana.',
            'dia_semana.between' => 'Elija un día de la semana válido.',
            'hora_inicio.required' => 'Indique la hora inicial.',
            'hora_inicio.date_format' => 'La hora inicial debe tener el formato HH:MM, por ejemplo 08:00.',
            'hora_fin.required' => 'Indique la hora final.',
            'hora_fin.date_format' => 'La hora final debe tener el formato HH:MM, por ejemplo 12:00.',
            'hora_fin.after' => 'La hora final debe ser posterior a la hora inicial.',
        ];
    }

    /**
     * Un médico no puede tener dos bloques superpuestos el mismo día. Dos bloques pegados
     * (uno termina cuando empieza el otro) no se superponen.
     */
    private function sinSuperposicion(?int $medicoId, ?int $ignorarId): \Closure
    {
        return function (string $atributo, mixed $horaFin, \Closure $fallar) use ($medicoId, $ignorarId): void {
            $dia = $this->input('dia_semana');
            $horaInicio = $this->input('hora_inicio');

            // Solo se consulta con datos que ya son válidos: las demás reglas informan el resto.
            // Una hora fuera de rango (por ejemplo 25:00) llegaría a la base y fallaría con un error técnico.
            $hora = '/^([01]\d|2[0-3]):[0-5]\d$/';

            if ($medicoId === null
                || ! is_numeric($dia) || (int) $dia < 1 || (int) $dia > 7
                || ! is_string($horaInicio) || ! preg_match($hora, $horaInicio)
                || ! is_string($horaFin) || ! preg_match($hora, $horaFin)) {
                return;
            }

            $choque = HorarioAtencion::query()
                ->where('medico_id', $medicoId)
                ->where('dia_semana', (int) $dia)
                ->where('hora_inicio', '<', $horaFin)
                ->where('hora_fin', '>', $horaInicio)
                ->when($ignorarId, fn ($consulta) => $consulta->where('id', '!=', $ignorarId))
                ->first();

            if ($choque !== null) {
                $fallar("Este horario se superpone con otro del mismo médico: {$choque->descripcion()}.");
            }
        };
    }
}

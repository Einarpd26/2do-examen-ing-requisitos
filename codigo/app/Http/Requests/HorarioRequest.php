<?php

namespace App\Http\Requests;

use App\Models\Consultorio;
use App\Models\HorarioAtencion;
use App\Models\Medico;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos del formulario de horario de atención (RF05): día, hora inicial, hora final y
 * consultorio (opcional, ADR-002). Sirve para registrar (la ruta trae el médico) y para modificar (trae el horario).
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
            'consultorio_id' => [
                'bail',
                'nullable',
                'integer',
                Rule::exists('consultorios', 'id')->where('estado', true),
                $this->consultorioLibre($horario?->medico_id ?? $medico?->id, $horario?->id),
            ],
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
            'consultorio_id.integer' => 'Elija un consultorio de la lista.',
            'consultorio_id.exists' => 'El consultorio elegido no existe o está desactivado.',
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

            if ($medicoId === null || ! $this->bloqueValido($dia, $horaInicio, $horaFin)) {
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

    /**
     * RN18: un consultorio no puede tener dos bloques superpuestos el mismo día. Se reserva durante
     * todo el bloque, tenga citas o no (ADR-002). Los choques con el propio médico ya los informa
     * la regla de la hora final, por eso aquí solo cuentan los bloques de otros médicos.
     */
    private function consultorioLibre(?int $medicoId, ?int $ignorarId): \Closure
    {
        return function (string $atributo, mixed $consultorioId, \Closure $fallar) use ($medicoId, $ignorarId): void {
            $dia = $this->input('dia_semana');
            $horaInicio = $this->input('hora_inicio');
            $horaFin = $this->input('hora_fin');

            if ($medicoId === null || ! $this->bloqueValido($dia, $horaInicio, $horaFin)) {
                return;
            }

            $choque = HorarioAtencion::query()
                ->with('medico.persona')
                ->where('consultorio_id', (int) $consultorioId)
                ->where('medico_id', '!=', $medicoId)
                ->where('dia_semana', (int) $dia)
                ->where('hora_inicio', '<', $horaFin)
                ->where('hora_fin', '>', $horaInicio)
                ->when($ignorarId, fn ($consulta) => $consulta->where('id', '!=', $ignorarId))
                ->first();

            if ($choque !== null) {
                $consultorio = Consultorio::find((int) $consultorioId)?->nombre ?? 'El consultorio';
                $fallar("{$consultorio} ya está ocupado ese día por {$choque->medico->persona->nombre_completo}: {$choque->descripcion()}.");
            }
        };
    }

    /**
     * Solo se consulta la base con datos que ya son válidos: las demás reglas informan el resto.
     * Una hora fuera de rango (por ejemplo 25:00) llegaría a la base y fallaría con un error técnico.
     */
    private function bloqueValido(mixed $dia, mixed $horaInicio, mixed $horaFin): bool
    {
        $hora = '/^([01]\d|2[0-3]):[0-5]\d$/';

        return is_numeric($dia) && (int) $dia >= 1 && (int) $dia <= 7
            && is_string($horaInicio) && preg_match($hora, $horaInicio) === 1
            && is_string($horaFin) && preg_match($hora, $horaFin) === 1;
    }
}

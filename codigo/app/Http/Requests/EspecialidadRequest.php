<?php

namespace App\Http\Requests;

use App\Models\Especialidad;
use App\Services\EspecialidadService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos del formulario de especialidad (RF04). Sirve para registrar y para modificar.
 */
class EspecialidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** El nombre se guarda sin espacios al inicio ni al final, y con un solo espacio entre palabras. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nombre'))) {
            $this->merge(['nombre' => trim(preg_replace('/\s+/', ' ', $this->input('nombre')))]);
        }
    }

    public function rules(): array
    {
        /** @var Especialidad|null $especialidad */
        $especialidad = $this->route('especialidad');

        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
                // Nombre normalizado: no se distinguen mayúsculas ni espacios de más (como el índice único).
                function (string $atributo, mixed $valor, \Closure $fallar) use ($especialidad): void {
                    $repetido = Especialidad::query()
                        ->whereRaw('lower(btrim(nombre)) = ?', [mb_strtolower(trim($valor))])
                        ->when($especialidad, fn ($consulta) => $consulta->where('id', '!=', $especialidad->id))
                        ->exists();

                    if ($repetido) {
                        $fallar(EspecialidadService::MENSAJE_NOMBRE_REPETIDO);
                    }
                },
            ],
            'descripcion' => ['nullable', 'string', 'max:300'],
            'duracion_min' => ['required', 'integer', 'between:5,240'],
        ];
    }

    public function messages(): array
    {
        return [
            'duracion_min.required' => 'Indique la duración de la cita, en minutos.',
            'duracion_min.integer' => 'La duración de la cita debe ser un número entero de minutos.',
            'duracion_min.between' => 'La duración de la cita debe estar entre :min y :max minutos.',
        ];
    }
}

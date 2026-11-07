<?php

namespace App\Http\Requests;

use App\Models\Consultorio;
use App\Models\Sucursal;
use App\Services\ConsultorioService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Datos del formulario de consultorio (CU-20). Sirve para registrar y para modificar.
 */
class ConsultorioRequest extends FormRequest
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
        /** @var Consultorio|null $consultorio */
        $consultorio = $this->route('consultorio');

        return [
            'nombre' => [
                'bail',
                'required',
                'string',
                'max:50',
                // Único dentro de la sucursal, sin distinguir mayúsculas ni espacios (como el índice único).
                function (string $atributo, mixed $valor, \Closure $fallar) use ($consultorio): void {
                    $repetido = Consultorio::query()
                        ->where('sucursal_id', $consultorio?->sucursal_id ?? Sucursal::predeterminada()->id)
                        ->whereRaw('lower(btrim(nombre)) = ?', [mb_strtolower(trim($valor))])
                        ->when($consultorio, fn ($consulta) => $consulta->where('id', '!=', $consultorio->id))
                        ->exists();

                    if ($repetido) {
                        $fallar(ConsultorioService::MENSAJE_NOMBRE_REPETIDO);
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Indique el nombre del consultorio.',
            'nombre.max' => 'El nombre del consultorio admite como máximo :max caracteres.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizaDatosDePersona;
use Illuminate\Foundation\Http\FormRequest;

/** CU-08: el paciente solo puede cambiar su teléfono; lo demás lo modifica la recepción. */
class PerfilRequest extends FormRequest
{
    use NormalizaDatosDePersona;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'telefono' => ['required', 'regex:/^\+?[0-9]{7,15}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'telefono.required' => 'Ingrese su número de celular.',
            'telefono.regex' => 'El teléfono debe tener de 7 a 15 dígitos, con un + inicial opcional.',
        ];
    }
}

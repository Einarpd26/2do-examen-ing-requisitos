<?php

namespace App\Http\Requests;

use App\Services\PacienteService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * CU-19: antes de renovar una contraseña hay que indicar cómo se comprobó la identidad del
 * titular. Es la defensa contra quien pide una contraseña ajena por teléfono.
 */
class RenovarAccesoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'verificacion' => ['required', Rule::in(array_keys(PacienteService::VERIFICACION_IDENTIDAD))],
        ];
    }

    public function messages(): array
    {
        return [
            'verificacion.required' => 'Indique cómo verificó la identidad del titular.',
            'verificacion.in' => 'Elija una de las formas de verificar la identidad.',
        ];
    }
}

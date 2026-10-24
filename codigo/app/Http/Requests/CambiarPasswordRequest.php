<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CambiarPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password_actual' => ['required', 'string'],
            'password' => [
                'required',
                'confirmed',
                'different:password_actual',
                Password::min(8)->mixedCase()->numbers(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'password_actual.required' => 'Ingrese su contraseña actual.',
            'password.required' => 'Ingrese la nueva contraseña.',
            'password.confirmed' => 'La confirmación no coincide con la nueva contraseña.',
            'password.different' => 'La nueva contraseña debe ser distinta de la actual.',
            'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.mixed' => 'La nueva contraseña debe combinar mayúsculas y minúsculas.',
            'password.numbers' => 'La nueva contraseña debe incluir al menos un número.',
        ];
    }
}

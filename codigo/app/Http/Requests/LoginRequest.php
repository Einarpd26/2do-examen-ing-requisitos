<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identificador' => ['required', 'string', 'max:150'],
            'contrasena' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'identificador.required' => 'Ingrese su CI o su correo.',
            'identificador.max' => 'El CI o el correo es demasiado largo.',
            'contrasena.required' => 'Ingrese su contraseña.',
        ];
    }
}

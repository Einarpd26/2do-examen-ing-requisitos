<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizaDatosDePersona;
use App\Models\Persona;
use App\Services\RegistroPacienteService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Datos del registro propio del paciente (CU-02): lo mínimo para crear la cuenta. El resto
 * de la ficha lo completa la recepcionista.
 */
class RegistroPacienteRequest extends FormRequest
{
    use NormalizaDatosDePersona;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ci' => [
                'required',
                'string',
                'regex:/^[0-9]{5,10}(-[0-9A-Z]{1,2})?$/',
                function (string $atributo, mixed $valor, \Closure $fallar): void {
                    // Cualquier persona con ese CI cuenta: paciente, médico o personal.
                    if (Persona::where('ci', $valor)->exists()) {
                        $fallar(RegistroPacienteService::mensajeCiRegistrado());
                    }
                },
            ],
            'primer_nombre' => ['required', 'string', 'max:100'],
            'segundo_nombre' => ['nullable', 'string', 'max:100'],
            'apellido_paterno' => ['required', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'telefono' => ['required', 'regex:/^\+?[0-9]{7,15}$/'],
            'correo' => [
                'nullable',
                'email',
                'max:150',
                function (string $atributo, mixed $valor, \Closure $fallar): void {
                    if (Persona::whereRaw('lower(correo) = ?', [strtolower((string) $valor)])->exists()) {
                        $fallar('Ese correo ya está registrado por otra persona.');
                    }
                },
            ],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ];
    }

    public function messages(): array
    {
        return [
            'ci.regex' => 'El CI debe tener de 5 a 10 dígitos, con un complemento opcional (por ejemplo 1234567-1A).',
            'telefono.required' => 'Ingrese su número de celular.',
            'telefono.regex' => 'El celular debe tener de 7 a 15 dígitos, con un + inicial opcional.',
            'password.confirmed' => 'La confirmación no coincide con la contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.mixed' => 'La contraseña debe combinar mayúsculas y minúsculas.',
            'password.numbers' => 'La contraseña debe incluir al menos un número.',
        ];
    }

    public function attributes(): array
    {
        return ['telefono' => 'celular', 'password' => 'contraseña'];
    }
}

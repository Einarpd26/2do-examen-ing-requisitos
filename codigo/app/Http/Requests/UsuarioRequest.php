<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizaDatosDePersona;
use App\Models\Persona;
use App\Models\Usuario;
use App\Services\UsuarioService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos del formulario de usuario del personal (RF01). Sirve para crear la cuenta y para
 * modificarla. Con el rol Médico no se piden datos personales: la cuenta se vincula a un
 * médico ya registrado (CU-05) y usa los suyos.
 */
class UsuarioRequest extends FormRequest
{
    use NormalizaDatosDePersona;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Usuario|null $cuenta */
        $cuenta = $this->route('usuario');

        // Los datos de una cuenta de médico se editan en Médicos: aquí no se valida nada y el
        // servicio responde con ese aviso.
        if ($cuenta?->rol->codigo === 'MEDICO') {
            return [];
        }

        $roles = $cuenta === null ? UsuarioService::ROLES_DE_PERSONAL : UsuarioService::ROLES_INTERCAMBIABLES;

        if ($cuenta === null && $this->input('rol') === 'MEDICO') {
            return [
                'rol' => ['required', Rule::in($roles)],
                'medico_id' => ['required', 'integer', 'exists:medicos,id'],
            ];
        }

        return [
            'rol' => ['required', Rule::in($roles)],
            'ci' => ['required', 'string', 'regex:/^[0-9]{5,10}(-[0-9A-Z]{1,2})?$/', $this->reglaCi($cuenta)],
            'primer_nombre' => ['required', 'string', 'max:100'],
            'segundo_nombre' => ['nullable', 'string', 'max:100'],
            'apellido_paterno' => ['required', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'regex:/^\+?[0-9]{7,15}$/'],
            'correo' => ['required', 'email', 'max:150', $this->reglaCorreo($cuenta)],
        ];
    }

    public function messages(): array
    {
        return [
            'rol.required' => 'Elija el rol de la cuenta.',
            'rol.in' => 'Elija uno de los roles disponibles.',
            'medico_id.required' => 'Elija el médico al que se vincula la cuenta.',
            'medico_id.exists' => 'El médico elegido no existe.',
            'ci.regex' => 'El CI debe tener de 5 a 10 dígitos, con un complemento opcional (por ejemplo 1234567-1A).',
            'telefono.regex' => 'El teléfono debe tener de 7 a 15 dígitos, con un + inicial opcional.',
            'correo.required' => 'Ingrese el correo: es el usuario con el que el personal entra al sistema.',
        ];
    }

    /** Al modificar, el CI no puede ser el de otra persona. Al crear, no puede ser el de una persona que ya tiene cuenta. */
    private function reglaCi(?Usuario $cuenta): \Closure|\Illuminate\Validation\Rules\Unique
    {
        if ($cuenta !== null) {
            return Rule::unique('personas', 'ci')->ignore($cuenta->persona_id);
        }

        return function (string $atributo, mixed $valor, \Closure $fallar): void {
            $persona = Persona::with('usuario.rol')->where('ci', $valor)->first();

            if ($persona?->usuario !== null) {
                $fallar("Esa persona ya tiene una cuenta con rol {$persona->usuario->rol->nombre}.");
            }
        };
    }

    /**
     * El correo es único sin distinguir mayúsculas. Al crear con un CI que ya existe como persona
     * se ignora su propio correo, porque sus datos personales no se modifican.
     */
    private function reglaCorreo(?Usuario $cuenta): \Closure
    {
        $personaId = $cuenta?->persona_id ?? Persona::where('ci', $this->input('ci'))->value('id');

        return function (string $atributo, mixed $valor, \Closure $fallar) use ($personaId): void {
            $existe = Persona::query()
                ->whereRaw('lower(correo) = ?', [strtolower((string) $valor)])
                ->when($personaId, fn ($consulta) => $consulta->where('id', '!=', $personaId))
                ->exists();

            if ($existe) {
                $fallar('Ese correo ya está registrado por otra persona.');
            }
        };
    }
}

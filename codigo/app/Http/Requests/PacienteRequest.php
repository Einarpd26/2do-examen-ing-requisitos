<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizaDatosDePersona;
use App\Models\Paciente;
use App\Models\Persona;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos del formulario de paciente (RF02, RF14): datos personales, información médica y
 * contacto de emergencia. Sirve para registrar y para modificar.
 */
class PacienteRequest extends FormRequest
{
    use NormalizaDatosDePersona;

    private const TELEFONO = '/^\+?[0-9]{7,15}$/';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Paciente|null $paciente */
        $paciente = $this->route('paciente');

        return [
            'ci' => ['required', 'string', 'regex:/^[0-9]{5,10}(-[0-9A-Z]{1,2})?$/', $this->reglaCi($paciente)],
            'primer_nombre' => ['required', 'string', 'max:100'],
            'segundo_nombre' => ['nullable', 'string', 'max:100'],
            'apellido_paterno' => ['required', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'fecha_nacimiento' => ['nullable', 'date', 'before_or_equal:today'],
            'sexo' => ['nullable', Rule::in(['M', 'F'])],
            'telefono' => ['nullable', 'regex:'.self::TELEFONO],
            'correo' => ['nullable', 'email', 'max:150', $this->reglaCorreo($paciente)],
            'direccion' => ['nullable', 'string', 'max:200'],

            'tipo_sangre' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'alergias' => ['nullable', 'string', 'max:2000'],
            'enfermedades_cronicas' => ['nullable', 'string', 'max:2000'],
            'medicamentos' => ['nullable', 'string', 'max:2000'],
            'antecedentes' => ['nullable', 'string', 'max:2000'],

            'emergencia_nombre' => ['nullable', 'string', 'max:150'],
            'emergencia_parentesco' => ['nullable', 'string', 'max:50'],
            'emergencia_telefono' => ['nullable', 'regex:'.self::TELEFONO],
        ];
    }

    public function messages(): array
    {
        return [
            'ci.regex' => 'El CI debe tener de 5 a 10 dígitos, con un complemento opcional (por ejemplo 1234567-1A).',
            'telefono.regex' => 'El teléfono debe tener de 7 a 15 dígitos, con un + inicial opcional.',
            'emergencia_telefono.regex' => 'El teléfono de emergencia debe tener de 7 a 15 dígitos, con un + inicial opcional.',
        ];
    }

    /** Al modificar, el CI no puede ser el de otra persona. Al registrar, no puede tener ya una ficha de paciente. */
    private function reglaCi(?Paciente $paciente): \Closure|\Illuminate\Validation\Rules\Unique
    {
        if ($paciente !== null) {
            return Rule::unique('personas', 'ci')->ignore($paciente->persona_id);
        }

        return function (string $atributo, mixed $valor, \Closure $fallar): void {
            if (Paciente::whereHas('persona', fn ($persona) => $persona->where('ci', $valor))->exists()) {
                $fallar('Ya existe un paciente registrado con este CI.');
            }
        };
    }

    /**
     * El correo es único sin distinguir mayúsculas. Al registrar con un CI que ya existe como
     * persona se ignora su propio correo, porque sus datos personales no se modifican.
     */
    private function reglaCorreo(?Paciente $paciente): \Closure
    {
        $personaId = $paciente?->persona_id ?? Persona::where('ci', $this->input('ci'))->value('id');

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

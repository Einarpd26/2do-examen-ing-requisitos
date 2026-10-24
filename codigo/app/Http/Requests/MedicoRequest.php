<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizaDatosDePersona;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\Persona;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos del formulario de médico (RF03): datos de la persona, registro profesional y
 * especialidades. Sirve para registrar y para modificar.
 */
class MedicoRequest extends FormRequest
{
    use NormalizaDatosDePersona {
        prepareForValidation as normalizarDatosDePersona;
    }

    public function authorize(): bool
    {
        return true;
    }

    /** Además de los datos de la persona, el registro profesional se guarda en mayúsculas y sin espacios de más. */
    protected function prepareForValidation(): void
    {
        $this->normalizarDatosDePersona();

        if (is_string($this->input('nro_registro_profesional'))) {
            $this->merge([
                'nro_registro_profesional' => strtoupper(trim(preg_replace('/\s+/', ' ', $this->input('nro_registro_profesional')))),
            ]);
        }
    }

    public function rules(): array
    {
        /** @var Medico|null $medico */
        $medico = $this->route('medico');

        return [
            'ci' => ['required', 'string', 'regex:/^[0-9]{5,10}(-[0-9A-Z]{1,2})?$/', $this->reglaCi($medico)],
            'primer_nombre' => ['required', 'string', 'max:100'],
            'segundo_nombre' => ['nullable', 'string', 'max:100'],
            'apellido_paterno' => ['required', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'telefono' => ['required', 'regex:/^\+?[0-9]{7,15}$/'],
            'correo' => ['required', 'email', 'max:150', $this->reglaCorreo($medico)],
            'nro_registro_profesional' => [
                'required',
                'string',
                'max:30',
                Rule::unique('medicos', 'nro_registro_profesional')->ignore($medico?->id),
            ],
            'especialidades' => ['required', 'array', 'min:1'],
            'especialidades.*' => ['integer', 'distinct', 'exists:especialidades,id', $this->reglaEspecialidad($medico)],
        ];
    }

    public function messages(): array
    {
        return [
            'ci.regex' => 'El CI debe tener de 5 a 10 dígitos, con un complemento opcional (por ejemplo 1234567-1A).',
            'telefono.required' => 'Ingrese el teléfono del médico.',
            'telefono.regex' => 'El teléfono debe tener de 7 a 15 dígitos, con un + inicial opcional.',
            'correo.required' => 'Ingrese el correo del médico: lo necesita para entrar al sistema.',
            'nro_registro_profesional.required' => 'Ingrese el número de registro profesional.',
            'nro_registro_profesional.unique' => 'Ese registro profesional ya pertenece a otro médico.',
            'especialidades.required' => 'Elija al menos una especialidad.',
            'especialidades.min' => 'Elija al menos una especialidad.',
            'especialidades.*.distinct' => 'Una especialidad está repetida.',
        ];
    }

    /** Al modificar, el CI no puede ser el de otra persona. Al registrar, no puede tener ya una ficha de médico. */
    private function reglaCi(?Medico $medico): \Closure|\Illuminate\Validation\Rules\Unique
    {
        if ($medico !== null) {
            return Rule::unique('personas', 'ci')->ignore($medico->persona_id);
        }

        return function (string $atributo, mixed $valor, \Closure $fallar): void {
            if (Medico::whereHas('persona', fn ($persona) => $persona->where('ci', $valor))->exists()) {
                $fallar('Ya existe un médico registrado con este CI.');
            }
        };
    }

    /**
     * El correo es único sin distinguir mayúsculas. Al registrar con un CI que ya existe como
     * persona se ignora su propio correo, porque sus datos personales no se modifican.
     */
    private function reglaCorreo(?Medico $medico): \Closure
    {
        $personaId = $medico?->persona_id ?? Persona::where('ci', $this->input('ci'))->value('id');

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

    /**
     * Solo se pueden asignar especialidades activas. Si el médico ya tenía una que luego se
     * desactivó, puede conservarla.
     */
    private function reglaEspecialidad(?Medico $medico): \Closure
    {
        $yaAsignadas = $medico?->especialidades()->pluck('especialidades.id')->all() ?? [];

        return function (string $atributo, mixed $valor, \Closure $fallar) use ($yaAsignadas): void {
            $activa = Especialidad::where('id', $valor)->where('estado', true)->exists();

            if (! $activa && ! in_array((int) $valor, $yaAsignadas, true)) {
                $fallar('Una de las especialidades elegidas está desactivada y no se puede asignar.');
            }
        };
    }
}

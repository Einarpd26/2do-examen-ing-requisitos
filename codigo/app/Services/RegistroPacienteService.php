<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Paciente;
use App\Models\Persona;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * CU-02: registro propio del paciente. Crea la persona, su ficha en estado pendiente de
 * verificación y su cuenta, todo junto. La contraseña la elige el paciente, así que no es
 * temporal. La recepcionista verifica la ficha después, con el documento (CU-04).
 */
class RegistroPacienteService
{
    public function __construct(private readonly BitacoraService $bitacora) {}

    /** @param  array<string, mixed>  $datos */
    public function registrar(array $datos): Usuario
    {
        try {
            return DB::transaction(function () use ($datos) {
                $persona = Persona::create(Arr::only($datos, [
                    'ci', 'primer_nombre', 'segundo_nombre', 'apellido_paterno', 'apellido_materno', 'telefono', 'correo',
                ]));

                Paciente::create(['persona_id' => $persona->id, 'verificado' => false]);

                $cuenta = Usuario::create([
                    'persona_id' => $persona->id,
                    'rol_id' => Rol::where('codigo', 'PACIENTE')->firstOrFail()->id,
                    'password_hash' => $datos['password'],
                ])->refresh();

                $this->bitacora->registrar('paciente_registro_propio', $cuenta, 'usuarios', $cuenta->id);

                return $cuenta;
            });
        } catch (UniqueConstraintViolationException) {
            // Otro registro con el mismo CI o correo ganó la carrera entre la validación y el guardado.
            throw new ReglaDeNegocioException(
                Persona::where('ci', $datos['ci'])->exists() ? self::mensajeCiRegistrado() : 'Ese correo ya está registrado por otra persona.'
            );
        }
    }

    /** Mensaje cuando el CI ya existe: quien lo posee debe pedir su acceso en recepción. */
    public static function mensajeCiRegistrado(): string
    {
        return 'Este CI ya está registrado. Si es suyo, pida su acceso en recepción: '.config('clinica.celular').'.';
    }
}

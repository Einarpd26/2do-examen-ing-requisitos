<?php

namespace Tests\Concerns;

use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Persona;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/**
 * Crea cuentas de prueba con un rol del catálogo (cargado por las migraciones).
 */
trait CreaCuentas
{
    protected const CLAVE = 'Clave-Segura-123';

    private static int $secuenciaCi = 1000000;

    /**
     * @param  array<string, mixed>  $persona  datos que reemplazan a los de la persona de prueba
     * @param  array<string, mixed>  $cuenta  datos que reemplazan a los de la cuenta de prueba
     */
    protected function crearCuenta(string $rol, array $persona = [], array $cuenta = []): Usuario
    {
        $ci = (string) ++self::$secuenciaCi;

        $persona = Persona::create(array_merge([
            'ci' => $ci,
            'primer_nombre' => 'Prueba',
            'apellido_paterno' => 'Cuenta',
            // El correo es obligatorio para el personal y opcional para el paciente.
            'correo' => $rol === 'PACIENTE' ? null : "cuenta{$ci}@clinica.test",
        ], $persona));

        // refresh() carga los valores por defecto de la base (estado, intentos, etc.).
        return Usuario::create(array_merge([
            'persona_id' => $persona->id,
            'rol_id' => Rol::where('codigo', $rol)->firstOrFail()->id,
            'password_hash' => self::CLAVE,
        ], $cuenta))->refresh()->load('persona', 'rol');
    }

    /** Mensaje de error del campo "identificador" tras la última solicitud. */
    protected function mensajeDeError(string $campo = 'identificador'): string
    {
        $errores = session('errors');

        if ($errores instanceof ViewErrorBag) {
            return $errores->getBag('default')->first($campo);
        }

        // Antes de leerse, los errores flasheados quedan como arreglo:
        // ['default' => ['format' => ..., 'messages' => ['campo' => ['mensaje']]]].
        $mensajes = is_array($errores) ? ($errores['default']['messages'] ?? []) : [];

        return (new MessageBag($mensajes))->first($campo);
    }

    /**
     * Crea una persona con su ficha de paciente (sin cuenta), verificada por defecto.
     *
     * @param  array<string, mixed>  $persona
     * @param  array<string, mixed>  $ficha
     */
    protected function crearPaciente(array $persona = [], array $ficha = []): Paciente
    {
        $persona = Persona::create(array_merge([
            'ci' => (string) ++self::$secuenciaCi,
            'primer_nombre' => 'Ana',
            'apellido_paterno' => 'Paz',
        ], $persona));

        return Paciente::create(array_merge([
            'persona_id' => $persona->id,
            'verificado' => true,
        ], $ficha))->refresh()->load('persona');
    }

    /**
     * Crea una persona con su ficha de médico (sin cuenta), activa por defecto y con las
     * especialidades indicadas (ids).
     *
     * @param  array<string, mixed>  $persona
     * @param  array<string, mixed>  $ficha
     * @param  array<int, int>  $especialidades
     */
    protected function crearFichaMedico(array $persona = [], array $ficha = [], array $especialidades = []): Medico
    {
        $persona = Persona::create(array_merge([
            'ci' => (string) ++self::$secuenciaCi,
            'primer_nombre' => 'Rosa',
            'apellido_paterno' => 'Luna',
            'telefono' => '70011122',
            'correo' => 'medico'.self::$secuenciaCi.'@clinica.test',
        ], $persona));

        $medico = Medico::create(array_merge([
            'persona_id' => $persona->id,
            'nro_registro_profesional' => 'MP-'.self::$secuenciaCi,
        ], $ficha))->refresh();

        $medico->especialidades()->sync($especialidades);

        return $medico->load('persona', 'especialidades');
    }

    /**
     * Datos válidos del formulario de paciente, con un CI nuevo en cada llamada.
     *
     * @param  array<string, mixed>  $reemplazo
     * @return array<string, mixed>
     */
    protected function datosPaciente(array $reemplazo = []): array
    {
        return array_merge([
            'ci' => (string) ++self::$secuenciaCi,
            'primer_nombre' => 'María',
            'segundo_nombre' => 'Elena',
            'apellido_paterno' => 'López',
            'apellido_materno' => 'Vega',
            'fecha_nacimiento' => '1990-05-12',
            'sexo' => 'F',
            'telefono' => '70012345',
            'correo' => 'maria'.self::$secuenciaCi.'@mail.com',
            'direccion' => 'Av. Principal 123',
            'tipo_sangre' => 'O+',
            'alergias' => 'Penicilina',
            'enfermedades_cronicas' => 'Hipertensión',
            'medicamentos' => 'Enalapril',
            'antecedentes' => 'Ninguno relevante',
            'emergencia_nombre' => 'Carlos López',
            'emergencia_parentesco' => 'Hermano',
            'emergencia_telefono' => '71234567',
        ], $reemplazo);
    }

    /** Datos del formulario de acceso. */
    protected function credenciales(string $identificador, string $contrasena = self::CLAVE): array
    {
        return ['identificador' => $identificador, 'contrasena' => $contrasena];
    }
}

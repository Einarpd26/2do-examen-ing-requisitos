<?php

namespace Tests\Concerns;

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

    /** Datos del formulario de acceso. */
    protected function credenciales(string $identificador, string $contrasena = self::CLAVE): array
    {
        return ['identificador' => $identificador, 'contrasena' => $contrasena];
    }
}

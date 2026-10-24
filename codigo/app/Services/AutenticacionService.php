<?php

namespace App\Services;

use App\Enums\ResultadoAcceso;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Reglas del inicio de sesión (CU-01): búsqueda de la cuenta por CI o correo, verificación
 * de la contraseña, bloqueo por intentos fallidos y control de la contraseña temporal.
 * No abre la sesión: eso lo hace el controlador con el resultado.
 */
class AutenticacionService
{
    private static ?string $hashDeRelleno = null;

    public function __construct(private readonly BitacoraService $bitacora) {}

    public function intentar(string $identificador, string $contrasena): IntentoAcceso
    {
        $cuenta = $this->buscarCuenta($identificador);

        if ($cuenta === null) {
            // Gasta el mismo tiempo que una verificación real, para no revelar si existe la cuenta.
            Hash::check($contrasena, self::hashDeRelleno());
            $this->bitacora->registrar('login_fallido');

            return new IntentoAcceso(ResultadoAcceso::CredencialesIncorrectas);
        }

        if ($cuenta->bloqueado_hasta?->isFuture()) {
            return new IntentoAcceso(ResultadoAcceso::Bloqueada, $cuenta, $this->minutosRestantes($cuenta));
        }

        if (! Hash::check($contrasena, $cuenta->password_hash)) {
            return $this->registrarFallo($cuenta);
        }

        if (! $cuenta->estado) {
            $this->bitacora->registrar('login_rechazado_inactiva', $cuenta, 'usuarios', $cuenta->id);

            return new IntentoAcceso(ResultadoAcceso::Inactiva, $cuenta);
        }

        if ($cuenta->debe_cambiar_password && $cuenta->password_temporal_vence->isPast()) {
            $this->bitacora->registrar('login_rechazado_temporal_vencida', $cuenta, 'usuarios', $cuenta->id);

            return new IntentoAcceso(ResultadoAcceso::TemporalVencida, $cuenta);
        }

        $cuenta->forceFill(['intentos_fallidos' => 0, 'bloqueado_hasta' => null])->save();
        $this->bitacora->registrar('login', $cuenta, 'usuarios', $cuenta->id);

        return new IntentoAcceso(ResultadoAcceso::Correcto, $cuenta);
    }

    /**
     * El paciente entra con su CI y el personal con su correo. Un identificador con "@" se
     * busca como correo; cualquier otro, como CI.
     */
    private function buscarCuenta(string $identificador): ?Usuario
    {
        $identificador = trim($identificador);
        $esCorreo = str_contains($identificador, '@');

        $cuenta = Usuario::query()
            ->with('rol')
            ->whereHas('persona', function ($persona) use ($esCorreo, $identificador) {
                if ($esCorreo) {
                    $persona->whereRaw('lower(correo) = ?', [Str::lower($identificador)]);
                } else {
                    // Igual que al registrarse: el CI se guarda sin espacios y en mayúsculas.
                    $persona->where('ci', Str::upper(preg_replace('/\s+/', '', $identificador)));
                }
            })
            ->first();

        if ($cuenta === null) {
            return null;
        }

        // Un paciente solo entra con CI y el personal solo con correo.
        $esPaciente = $cuenta->rol->codigo === 'PACIENTE';

        return $esPaciente !== $esCorreo ? $cuenta : null;
    }

    private function registrarFallo(Usuario $cuenta): IntentoAcceso
    {
        // increment() suma en la base de forma atómica; se vuelve a leer el valor real porque
        // con intentos simultáneos el que tiene en memoria este request ya quedó desactualizado.
        $cuenta->increment('intentos_fallidos');
        $cuenta->refresh();

        if ($cuenta->intentos_fallidos >= config('clinica.login.max_intentos')) {
            $cuenta->forceFill([
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => now()->addMinutes(config('clinica.login.bloqueo_minutos')),
            ])->save();
            $this->bitacora->registrar('cuenta_bloqueada', $cuenta, 'usuarios', $cuenta->id);

            return new IntentoAcceso(ResultadoAcceso::Bloqueada, $cuenta, $this->minutosRestantes($cuenta));
        }

        $this->bitacora->registrar('login_fallido', $cuenta, 'usuarios', $cuenta->id);

        return new IntentoAcceso(ResultadoAcceso::CredencialesIncorrectas, $cuenta);
    }

    private function minutosRestantes(Usuario $cuenta): int
    {
        return max(1, (int) ceil(now()->diffInSeconds($cuenta->bloqueado_hasta, false) / 60));
    }

    private static function hashDeRelleno(): string
    {
        return self::$hashDeRelleno ??= Hash::make(Str::random(32));
    }
}

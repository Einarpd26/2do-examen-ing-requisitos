<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Se ejecuta en cada solicitud de una sesión abierta:
 * - cierra la sesión si la cuenta fue desactivada (la baja surte efecto de inmediato);
 * - cierra la sesión si la contraseña temporal venció;
 * - obliga a cambiar la contraseña temporal antes de usar el sistema (RNF18).
 */
class VerificarCuenta
{
    /** Rutas que se pueden usar mientras falta cambiar la contraseña temporal. */
    private const RUTAS_PERMITIDAS = ['password.cambiar', 'password.actualizar', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $cuenta = $request->user();

        if ($cuenta === null) {
            return $next($request);
        }

        if (! $cuenta->estado) {
            return $this->cerrarSesion($request, 'Su cuenta fue desactivada. Comuníquese con recepción.');
        }

        if ($cuenta->debe_cambiar_password) {
            if ($cuenta->password_temporal_vence->isPast()) {
                return $this->cerrarSesion(
                    $request,
                    'La contraseña temporal venció. Pida una nueva en recepción: '.config('clinica.celular').'.'
                );
            }

            if (! $request->routeIs(self::RUTAS_PERMITIDAS)) {
                return redirect()->route('password.cambiar');
            }
        }

        return $next($request);
    }

    private function cerrarSesion(Request $request, string $mensaje): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['identificador' => $mensaje]);
    }
}

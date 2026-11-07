<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las páginas de una sesión iniciada no se guardan en la caché del navegador. Sin esto, después
 * de cerrar sesión el botón «Atrás» vuelve a mostrar la última página privada (por ejemplo, el
 * perfil del paciente con sus datos) desde la caché, aunque el servidor ya cerró la sesión.
 * «no-store» también saca la página de la caché de ida y vuelta (bfcache) de los navegadores.
 */
class SinCacheConSesion
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() !== null) {
            $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}

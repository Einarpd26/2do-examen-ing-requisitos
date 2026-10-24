<?php

use App\Exceptions\ReglaDeNegocioException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'cuenta.vigente' => \App\Http\Middleware\VerificarCuenta::class,
        ]);

        // Quien ya inició sesión no vuelve al formulario de acceso.
        $middleware->redirectUsersTo(fn () => route('inicio'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Una regla del negocio incumplida vuelve a la pantalla anterior con su mensaje.
        // Las contraseñas escritas no vuelven al formulario.
        $exceptions->render(
            fn (ReglaDeNegocioException $e) => back()
                ->withInput(request()->except(['password', 'password_confirmation', 'password_actual', 'contrasena']))
                ->with('error', $e->getMessage())
        );

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

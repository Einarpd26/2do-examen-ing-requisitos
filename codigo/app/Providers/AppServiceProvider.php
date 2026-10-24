<?php

namespace App\Providers;

use App\Models\Usuario;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Cada permiso del catálogo (por ejemplo pacientes:gestionar) funciona como una
        // habilidad: Gate::allows(), @can() y el middleware can: lo consultan en el rol de la cuenta.
        Gate::before(fn (?Usuario $cuenta, string $habilidad) => $cuenta?->tienePermiso($habilidad) ? true : null);

        // Las rutas de /usuarios solo alcanzan cuentas del personal: las de pacientes se gestionan
        // en CU-02 y CU-04, así que aquí responden 404.
        Route::bind('usuario', fn (string $valor) => Usuario::query()
            ->whereHas('rol', fn ($rol) => $rol->where('codigo', '!=', 'PACIENTE'))
            ->findOrFail($valor));

        // Los enlaces de paginación usan el estilo de Bootstrap 5.
        Paginator::useBootstrapFive();
    }
}

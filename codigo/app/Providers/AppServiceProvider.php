<?php

namespace App\Providers;

use App\Models\Usuario;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
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

        // Los enlaces de paginación usan el estilo de Bootstrap 5.
        Paginator::useBootstrapFive();
    }
}

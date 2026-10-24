<?php

use App\Http\Controllers\AccesoController;
use App\Http\Controllers\CambioPasswordController;
use App\Http\Controllers\InicioController;
use Illuminate\Support\Facades\Route;

// Acceso público: solo para quien no tiene sesión.
Route::middleware('guest')->group(function () {
    Route::get('/login', [AccesoController::class, 'formulario'])->name('login');
    Route::post('/login', [AccesoController::class, 'autenticar'])->name('login.autenticar');
});

// Páginas internas: exigen sesión válida (RF01) y una cuenta vigente.
Route::middleware(['auth', 'cuenta.vigente'])->group(function () {
    Route::redirect('/', '/inicio');
    Route::get('/inicio', InicioController::class)->name('inicio');
    Route::post('/logout', [AccesoController::class, 'salir'])->name('logout');

    Route::get('/cambiar-password', [CambioPasswordController::class, 'formulario'])->name('password.cambiar');
    Route::put('/cambiar-password', [CambioPasswordController::class, 'actualizar'])->name('password.actualizar');
});

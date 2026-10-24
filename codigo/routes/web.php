<?php

use App\Http\Controllers\AccesoController;
use App\Http\Controllers\AccesoPacienteController;
use App\Http\Controllers\CambioPasswordController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\PacienteController;
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

    // CU-04: gestionar pacientes. Todo el grupo exige pacientes:gestionar; las acciones
    // sensibles exigen además su propio permiso.
    Route::prefix('pacientes')->name('pacientes.')->middleware('can:pacientes:gestionar')->group(function () {
        Route::get('/', [PacienteController::class, 'index'])->name('index');
        Route::get('/nuevo', [PacienteController::class, 'create'])->name('create');
        Route::post('/', [PacienteController::class, 'store'])->name('store');

        Route::prefix('{paciente}')->where(['paciente' => '[0-9]+'])->group(function () {
            Route::get('/', [PacienteController::class, 'show'])->name('show');
            Route::get('/editar', [PacienteController::class, 'edit'])->name('edit');
            Route::put('/', [PacienteController::class, 'update'])->name('update');
            Route::patch('/estado', [PacienteController::class, 'estado'])->name('estado');

            Route::post('/verificar', [AccesoPacienteController::class, 'verificar'])
                ->name('verificar')->middleware('can:pacientes:verificar');
            Route::post('/acceso', [AccesoPacienteController::class, 'crear'])
                ->name('acceso.crear')->middleware('can:pacientes:crear_acceso');
            Route::post('/acceso/renovar', [AccesoPacienteController::class, 'renovar'])
                ->name('acceso.renovar')->middleware('can:accesos:renovar');
            Route::get('/credenciales', [AccesoPacienteController::class, 'credenciales'])->name('credenciales');
        });
    });
});

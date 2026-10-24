<?php

use App\Http\Controllers\AccesoController;
use App\Http\Controllers\AccesoPacienteController;
use App\Http\Controllers\CambioPasswordController;
use App\Http\Controllers\EspecialidadController;
use App\Http\Controllers\HorarioController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\MedicoController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\RegistroController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

// Acceso público: solo para quien no tiene sesión.
Route::middleware('guest')->group(function () {
    Route::get('/login', [AccesoController::class, 'formulario'])->name('login');
    Route::post('/login', [AccesoController::class, 'autenticar'])->name('login.autenticar');

    // CU-02: registro propio del paciente. Limitado a 10 solicitudes por minuto por IP.
    Route::get('/registro', [RegistroController::class, 'formulario'])->name('registro.formulario');
    Route::post('/registro', [RegistroController::class, 'guardar'])
        ->middleware('throttle:10,1')->name('registro.guardar');
});

// Páginas internas: exigen sesión válida (RF01) y una cuenta vigente.
Route::middleware(['auth', 'cuenta.vigente'])->group(function () {
    Route::redirect('/', '/inicio');
    Route::get('/inicio', InicioController::class)->name('inicio');
    Route::post('/logout', [AccesoController::class, 'salir'])->name('logout');

    Route::get('/cambiar-password', [CambioPasswordController::class, 'formulario'])->name('password.cambiar');
    Route::put('/cambiar-password', [CambioPasswordController::class, 'actualizar'])->name('password.actualizar');

    // CU-03: gestionar usuarios y roles del personal (solo el Administrador, con usuarios:gestionar).
    Route::prefix('usuarios')->name('usuarios.')->middleware('can:usuarios:gestionar')->group(function () {
        Route::get('/', [UsuarioController::class, 'index'])->name('index');
        Route::get('/nuevo', [UsuarioController::class, 'create'])->name('create');
        Route::post('/', [UsuarioController::class, 'store'])->name('store');

        Route::prefix('{usuario}')->where(['usuario' => '[0-9]+'])->group(function () {
            Route::get('/editar', [UsuarioController::class, 'edit'])->name('edit');
            Route::put('/', [UsuarioController::class, 'update'])->name('update');
            Route::patch('/estado', [UsuarioController::class, 'estado'])->name('estado');
            Route::post('/renovar', [UsuarioController::class, 'renovar'])
                ->name('renovar')->middleware('can:accesos:renovar');
            Route::get('/credenciales', [UsuarioController::class, 'credenciales'])->name('credenciales');
        });
    });

    // CU-07: gestionar horarios de atención (solo el Administrador, con horarios:gestionar).
    Route::prefix('horarios')->name('horarios.')->middleware('can:horarios:gestionar')->group(function () {
        Route::get('/', [HorarioController::class, 'index'])->name('index');
        Route::get('/medico/{medico}', [HorarioController::class, 'medico'])
            ->where(['medico' => '[0-9]+'])->name('medico');
        Route::post('/medico/{medico}', [HorarioController::class, 'store'])
            ->where(['medico' => '[0-9]+'])->name('store');

        Route::prefix('{horario}')->where(['horario' => '[0-9]+'])->group(function () {
            Route::get('/editar', [HorarioController::class, 'edit'])->name('edit');
            Route::put('/', [HorarioController::class, 'update'])->name('update');
            Route::delete('/', [HorarioController::class, 'destroy'])->name('destroy');
        });
    });

    // CU-05: gestionar médicos (solo el Administrador, con medicos:gestionar).
    Route::prefix('medicos')->name('medicos.')->middleware('can:medicos:gestionar')->group(function () {
        Route::get('/', [MedicoController::class, 'index'])->name('index');
        Route::get('/nuevo', [MedicoController::class, 'create'])->name('create');
        Route::post('/', [MedicoController::class, 'store'])->name('store');

        Route::prefix('{medico}')->where(['medico' => '[0-9]+'])->group(function () {
            Route::get('/editar', [MedicoController::class, 'edit'])->name('edit');
            Route::put('/', [MedicoController::class, 'update'])->name('update');
            Route::patch('/estado', [MedicoController::class, 'estado'])->name('estado');
        });
    });

    // CU-06: gestionar especialidades (solo el Administrador, con especialidades:gestionar).
    Route::prefix('especialidades')->name('especialidades.')->middleware('can:especialidades:gestionar')->group(function () {
        Route::get('/', [EspecialidadController::class, 'index'])->name('index');
        Route::get('/nueva', [EspecialidadController::class, 'create'])->name('create');
        Route::post('/', [EspecialidadController::class, 'store'])->name('store');

        Route::prefix('{especialidad}')->where(['especialidad' => '[0-9]+'])->group(function () {
            Route::get('/editar', [EspecialidadController::class, 'edit'])->name('edit');
            Route::put('/', [EspecialidadController::class, 'update'])->name('update');
            Route::patch('/estado', [EspecialidadController::class, 'estado'])->name('estado');
        });
    });

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

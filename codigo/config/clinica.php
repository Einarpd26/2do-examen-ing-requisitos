<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Administrador inicial
    |--------------------------------------------------------------------------
    |
    | Datos de la primera cuenta, que crea AdministradorInicialSeeder. El Administrador
    | entra con su correo. Cámbielos en .env antes de cargar los datos iniciales.
    |
    */

    'admin' => [
        'ci' => env('ADMIN_CI', '1000001'),
        'primer_nombre' => env('ADMIN_PRIMER_NOMBRE', 'Administrador'),
        'apellido_paterno' => env('ADMIN_APELLIDO_PATERNO', 'Sistema'),
        'correo' => env('ADMIN_CORREO', 'admin@clinicavidasalud.test'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Contacto de la clínica
    |--------------------------------------------------------------------------
    |
    | Celular que se muestra en el inicio de sesión para pedir a recepción una
    | contraseña temporal nueva.
    |
    */

    'celular' => env('CLINICA_CELULAR', '+591 70000000'),

    /*
    |--------------------------------------------------------------------------
    | Inicio de sesión
    |--------------------------------------------------------------------------
    |
    | Tras max_intentos contraseñas incorrectas seguidas, la cuenta se bloquea durante
    | bloqueo_minutos (RNF15). El cierre por inactividad (30 minutos, RNF14) lo fija
    | SESSION_LIFETIME en .env.
    |
    */

    'login' => [
        'max_intentos' => 5,
        'bloqueo_minutos' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Citas
    |--------------------------------------------------------------------------
    |
    | Una cita se puede registrar hasta dias_anticipacion_max días después de hoy
    | (hora de La Paz), para cualquier rol (CU-09). El Paciente solo puede cancelar con
    | al menos horas_min_cancelacion_paciente horas de anticipación (RN15, CU-11); la
    | Recepcionista no tiene esa restricción.
    |
    */

    'citas' => [
        'dias_anticipacion_max' => 60,
        'horas_min_cancelacion_paciente' => 24,
        // Días hacia adelante que cubre el aviso de próximas citas del Paciente (CU-18).
        'recordatorio_dias' => 2,
    ],

];

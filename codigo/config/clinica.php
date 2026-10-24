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

];

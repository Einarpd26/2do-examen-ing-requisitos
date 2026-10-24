<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Los catálogos (sucursal, roles y permisos) los carga la migración
     * 003_catalogos_iniciales; aquí solo se crea la cuenta inicial.
     */
    public function run(): void
    {
        $this->call(AdministradorInicialSeeder::class);
    }
}

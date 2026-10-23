<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Ciclo #1: el esquema vive en SQL puro (database/sql) y la migración solo lo ejecuta.
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/001_esquema_base.sql')));
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS bitacora, horarios_atencion, medico_especialidad, especialidades, medicos, pacientes, usuarios, personas, sucursales CASCADE');
    }
};

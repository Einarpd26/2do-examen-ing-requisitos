<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Datos de referencia (sucursal, roles, permisos) en SQL puro (database/sql).
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/003_catalogos_iniciales.sql')));
    }

    public function down(): void
    {
        DB::unprepared('DELETE FROM rol_permisos');
        DB::unprepared('DELETE FROM permisos');
        DB::unprepared('DELETE FROM roles');
        DB::unprepared("DELETE FROM sucursales WHERE nombre = 'Central'");
    }
};

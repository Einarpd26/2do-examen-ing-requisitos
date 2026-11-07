<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Ciclo #2 (CU-09): restricción de exclusión por paciente, también en SQL puro (database/sql).
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/004_citas_sin_superposicion_paciente.sql')));
    }

    public function down(): void
    {
        DB::unprepared('ALTER TABLE citas DROP CONSTRAINT IF EXISTS citas_paciente_sin_superposicion');
    }
};

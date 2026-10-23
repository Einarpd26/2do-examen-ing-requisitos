<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Ciclo #2: citas y notas clínicas, también en SQL puro (database/sql).
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/002_citas_y_notas.sql')));
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS notas_clinicas, citas CASCADE');
    }
};

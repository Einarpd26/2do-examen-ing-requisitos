<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ADR-002 (CU-20, RN18): consultorios como entidad propia, también en SQL puro (database/sql).
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/005_consultorios.sql')));
    }

    /** Vuelve al texto libre: cada bloque recupera el nombre de su consultorio. */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE horarios_atencion DROP CONSTRAINT IF EXISTS horarios_consultorio_sin_superposicion;
            ALTER TABLE horarios_atencion DROP CONSTRAINT IF EXISTS horarios_atencion_consultorio_fk;
            ALTER TABLE horarios_atencion ADD COLUMN consultorio TEXT;
            UPDATE horarios_atencion h SET consultorio = c.nombre FROM consultorios c WHERE c.id = h.consultorio_id;
            ALTER TABLE horarios_atencion DROP COLUMN consultorio_id;
            DROP TABLE consultorios;
            DELETE FROM rol_permisos WHERE permiso_id IN (SELECT id FROM permisos WHERE codigo = 'consultorios:gestionar');
            DELETE FROM permisos WHERE codigo = 'consultorios:gestionar';
            SQL);
    }
};

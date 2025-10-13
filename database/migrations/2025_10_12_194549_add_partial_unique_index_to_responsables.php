<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('responsables')) {
            return; // ← evita fallar si aún no existe
        }

        // Índice normal sobre activo (opcional)
        DB::statement("CREATE INDEX IF NOT EXISTS responsables_activo_index ON responsables (activo)");

        // Índice único parcial (PostgreSQL): un activo por (área, nivel)
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS responsables_area_nivel_activo_unico
            ON responsables (area_id, nivel_id)
            WHERE activo = true
        ");
    }

    public function down(): void
    {
        if (!Schema::hasTable('responsables')) return;

        DB::statement("DROP INDEX IF EXISTS responsables_activo_index");
        DB::statement("DROP INDEX IF EXISTS responsables_area_nivel_activo_unico");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('responsables', function (Blueprint $table) {
            $table->id();
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('ci')->unique();
            $table->string('correo')->unique();
            $table->string('telefono')->nullable();

            $table->foreignId('area_id')
                  ->constrained('areas')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            $table->foreignId('nivel_id')
                  ->nullable()
                  ->constrained('niveles')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();

            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Índice normal (opcional)
            $table->index('activo', 'responsables_activo_idx');
        });

        // Índice único parcial (PostgreSQL): un activo por (área, nivel)
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS responsables_area_nivel_activo_unico
            ON responsables (area_id, nivel_id)
            WHERE activo = true
        ");
    }

    public function down(): void {
        DB::statement("DROP INDEX IF EXISTS responsables_area_nivel_activo_unico");
        Schema::dropIfExists('responsables');
    }
};

<?php
// database/migrations/2025_10_30_000001_create_finalistas_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('finalistas', function (Blueprint $t) {
            $t->id();

            // Relaciones principales (válidas)
            $t->foreignId('inscrito_id')->constrained('inscritos')->cascadeOnDelete();
            $t->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $t->foreignId('nivel_id')->nullable()->constrained('niveles')->nullOnDelete();

            // Cierre opcional: SIN FK porque no existe la tabla 'clasificaciones'.
            // Dejamos el campo por si luego lo usas.
            $t->unsignedBigInteger('cierre_id')->nullable()->index();

            // Identificador del lote (sha256 => 64 chars)
            $t->string('origen_hash', 64)->index();

            // Fecha de habilitación (requerida)
            $t->timestamp('habilitado_at');

            // Unicidad por inscrito + lote
            $t->unique(['inscrito_id', 'origen_hash'], 'finalistas_unique_inscrito_hash');
        });
    }

    public function down(): void {
        Schema::dropIfExists('finalistas');
    }
};

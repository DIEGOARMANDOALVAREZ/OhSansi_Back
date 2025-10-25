<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('note_change_logs', function (Blueprint $table) {
            $table->id();

            // Momento exacto del cambio
            $table->timestamp('occurred_at')->index();

            // Quién ejecutó el cambio (auditor/evaluador/responsable). Nullable por si fue proceso automático.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Competidor/inscrito afectado
            $table->foreignId('competidor_id')->constrained('inscritos')->cascadeOnDelete();

            // Denormalización para filtros rápidos (área/nivel del competidor al momento del cambio)
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->foreignId('nivel_id')->nullable()->constrained('niveles')->nullOnDelete();

            // Campo modificado y valores
            $table->string('campo', 64);            // p.ej. 'nota', 'estado', 'reapertura'
            $table->text('anterior')->nullable();   // se guarda en string (serializado simple)
            $table->text('nuevo')->nullable();      // idem
            $table->text('motivo')->nullable();     // motivo si aplica

            $table->timestamps();

            // No SoftDeletes: este log NO se puede borrar (criterio 4)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_change_logs');
    }
};

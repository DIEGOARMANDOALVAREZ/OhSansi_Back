<?php
// database/migrations/2025_10_31_000003_create_evaluaciones_finales_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('evaluaciones_finales', function (Blueprint $t) {
      $t->id();
      $t->foreignId('finalista_id')->constrained('finalistas')->cascadeOnDelete();
      $t->foreignId('evaluador_id')->constrained('evaluadores')->cascadeOnDelete();
      $t->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
      $t->foreignId('nivel_id')->nullable()->constrained('niveles')->nullOnDelete();

      $t->json('notas')->nullable();         // estructura flexible
      $t->decimal('nota_final', 5, 2)->nullable();
      $t->string('concepto', 32)->nullable(); // APROBADO/DESAPROBADO si aplica o “CALIFICADO”
      $t->string('estado', 24)->default('EN_EDICION'); // EN_EDICION | FINALIZADA
      $t->timestamp('finalizado_at')->nullable();

      // Auditoría mínima
      $t->timestamps();

      $t->unique(['finalista_id','evaluador_id'], 'eval_final_unique_finalista_eval');
      $t->index(['area_id','nivel_id']);
    });
  }

  public function down(): void {
    Schema::dropIfExists('evaluaciones_finales');
  }
};

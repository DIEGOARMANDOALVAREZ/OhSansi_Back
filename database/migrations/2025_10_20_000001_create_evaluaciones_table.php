<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('evaluaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inscrito_id');   // 👈 FK al competidor/inscrito
            $table->unsignedBigInteger('evaluador_id');  // 👈 FK al evaluador
            $table->unsignedBigInteger('area_id');
            $table->unsignedBigInteger('nivel_id')->nullable();

            // Notas y resultado
            $table->json('notas')->nullable();           // { criterio: puntaje, ... }
            $table->decimal('nota_final', 5, 2)->nullable();
            $table->string('concepto', 32)->nullable();  // APROBADO | DESAPROBADO | DESCLASIFICADO
            $table->text('observaciones')->nullable();

            // Estado
            $table->string('estado', 16)->default('borrador'); // borrador | finalizado
            $table->timestamp('finalizado_at')->nullable();

            $table->timestamps();

            $table->foreign('inscrito_id')->references('id')->on('inscritos')->onDelete('cascade');
            $table->foreign('evaluador_id')->references('id')->on('evaluadores')->onDelete('cascade');

            // Evita duplicados por inscrito+evaluador (una evaluación por evaluador)
            $table->unique(['inscrito_id','evaluador_id']);
            $table->index(['area_id','nivel_id','estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones');
    }
};

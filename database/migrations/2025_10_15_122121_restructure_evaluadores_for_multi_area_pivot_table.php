<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones (UP).
     */
    public function up(): void
    {
        // 1. Eliminar las claves foráneas y columnas directas de la tabla evaluadores.
        Schema::table('evaluadores', function (Blueprint $table) {
            // Eliminar las claves foráneas primero para evitar errores
            $table->dropConstrainedForeignId('area_id');
            $table->dropConstrainedForeignId('nivel_id');

            // Luego, eliminar las columnas
            // Si las columnas no existieran, esto podría fallar, pero dado tu archivo original, deben existir.
            // Si el dropConstrainedForeignId ya eliminó las columnas, puedes comentar las siguientes 2 líneas.
            // $table->dropColumn('area_id');
            // $table->dropColumn('nivel_id');
        });

        // 2. Crear la tabla pivote para la relación muchos-a-muchos
        // Esta tabla manejará la asociación de un Evaluador con una o más Áreas
        Schema::create('evaluador_area', function (Blueprint $table) {
            // Claves foráneas compuestas que forman la clave primaria
            $table->foreignId('evaluador_id')->constrained('evaluadores')->onDelete('cascade');
            $table->foreignId('area_id')->constrained('areas')->onDelete('cascade');
            
            // Columna extra (pivot data) para el nivel, **DEFINIDA COMO NULLABLE**
            // Esto resuelve el error 500 si se intenta guardar sin un nivel.
            $table->foreignId('nivel_id')->nullable()->constrained('niveles')->onDelete('set null');

            // Establecer la clave primaria compuesta (evaluador_id, area_id)
            $table->primary(['evaluador_id', 'area_id']);
            
            $table->timestamps();
        });
    }

    /**
     * Revierte las migraciones (DOWN).
     */
    public function down(): void
    {
        // 1. Eliminar la tabla pivote
        Schema::dropIfExists('evaluador_area');

        // 2. Revertir la tabla evaluadores (Opcional, solo si necesitas reversibilidad completa)
        // En una aplicación real, probablemente dejarías la tabla evaluadores como está en DOWN.
        Schema::table('evaluadores', function (Blueprint $table) {
            $table->foreignId('area_id')->nullable()->constrained('areas');
            $table->foreignId('nivel_id')->nullable()->constrained('niveles');
        });
    }
};

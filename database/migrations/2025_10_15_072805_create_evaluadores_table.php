<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('evaluadores', function (Blueprint $table) {
            $table->id();

            $table->string('nombres');
            $table->string('apellidos');
            $table->string('correo', 120)->unique();
            $table->string('telefono', 20)->nullable();

            // 👇 CI obligatorio, único
            $table->string('ci', 32)->unique();

            // relaciones a catálogos
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        /**
         * ⚙️ Tabla pivote para asociaciones
         * evaluador_id ↔ área_id (con nivel_id intermedio)
         */
        Schema::create('area_evaluador', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluador_id')->constrained('evaluadores')->onDelete('cascade');
            $table->foreignId('area_id')->constrained('areas')->onDelete('cascade');
            $table->foreignId('nivel_id')->nullable()->constrained('niveles')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_evaluador');
        Schema::dropIfExists('evaluadores');
    }
};

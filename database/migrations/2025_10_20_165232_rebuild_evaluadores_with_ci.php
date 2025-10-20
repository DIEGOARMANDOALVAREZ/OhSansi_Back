<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuild de la tabla evaluadores con campo CI y sus dependencias.
 * Seguro para Postgres: suelta FKs -> drop tablas dependientes -> recrea todo -> reatacha FKs.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────────────────────
        // 0) Deshabilitar FKs (Laravel lo soporta para Postgres)
        // ─────────────────────────────────────────────────────────────
        Schema::disableForeignKeyConstraints();

        // ─────────────────────────────────────────────────────────────
        // 1) Soltar FKs/Tablas que dependen de evaluadores
        //    - evaluador_area (pivote)
        //    - evaluador_tokens (tokens de login)
        //    - evaluaciones.evaluador_id (FK suelta, NO borramos toda la tabla)
        // ─────────────────────────────────────────────────────────────

        // 1.a) Pivot evaluador_area (nombres reales vistos en tu error)
        if (Schema::hasTable('evaluador_area')) {
            // Dropear tabla pivote completa (más fácil/limpio)
            Schema::drop('evaluador_area');
        }

        // 1.b) Tokens
        if (Schema::hasTable('evaluador_tokens')) {
            Schema::drop('evaluador_tokens');
        }

        // 1.c) Quitar FK en evaluaciones.evaluador_id si existe
        if (Schema::hasTable('evaluaciones') && Schema::hasColumn('evaluaciones', 'evaluador_id')) {
            try {
                Schema::table('evaluaciones', function (Blueprint $table) {
                    // El nombre de la FK suele ser {tabla}_{columna}_foreign
                    $table->dropForeign(['evaluador_id']);
                });
            } catch (\Throwable $e) {
                // Silencioso si no existe la FK
            }
        }

        // ─────────────────────────────────────────────────────────────
        // 2) Dropear tabla evaluadores
        // ─────────────────────────────────────────────────────────────
        if (Schema::hasTable('evaluadores')) {
            Schema::drop('evaluadores');
        }

        // ─────────────────────────────────────────────────────────────
        // 3) Recrear evaluadores (ahora con CI único)
        // ─────────────────────────────────────────────────────────────
        Schema::create('evaluadores', function (Blueprint $table) {
            $table->id();
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('correo', 120)->unique();
            $table->string('telefono', 20)->nullable();

            // 👇 Nuevo campo CI (obligatorio y único)
            $table->string('ci', 32)->unique();

            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // ─────────────────────────────────────────────────────────────
        // 4) Recrear pivote evaluador_area (con nivel_id)
        //    Nota: Usamos el mismo nombre que ya tenías: evaluador_area
        // ─────────────────────────────────────────────────────────────
        Schema::create('evaluador_area', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluador_id')->constrained('evaluadores')->onDelete('cascade');
            $table->foreignId('area_id')->constrained('areas')->onDelete('cascade');
            $table->foreignId('nivel_id')->nullable()->constrained('niveles')->nullOnDelete();
            $table->timestamps();

            $table->unique(['evaluador_id', 'area_id'], 'ev_area_unique'); // evita duplicados
        });

        // ─────────────────────────────────────────────────────────────
        // 5) Recrear evaluador_tokens
        // ─────────────────────────────────────────────────────────────
        Schema::create('evaluador_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluador_id')->constrained('evaluadores')->onDelete('cascade');
            $table->string('name', 50)->nullable();
            $table->string('token', 64)->unique(); // hash sha256 en tu backend
            $table->json('abilities')->nullable();
            $table->timestamps();
        });

        // ─────────────────────────────────────────────────────────────
        // 6) Reatachar FK de evaluaciones.evaluador_id → evaluadores.id
        // ─────────────────────────────────────────────────────────────
        if (Schema::hasTable('evaluaciones') && Schema::hasColumn('evaluaciones', 'evaluador_id')) {
            Schema::table('evaluaciones', function (Blueprint $table) {
                $table->foreign('evaluador_id')
                    ->references('id')->on('evaluadores')
                    ->onDelete('restrict'); // o cascade/nullOnDelete según tu lógica
            });
        }

        // ─────────────────────────────────────────────────────────────
        // 7) Rehabilitar FKs
        // ─────────────────────────────────────────────────────────────
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        // Quitar FK en evaluaciones
        if (Schema::hasTable('evaluaciones') && Schema::hasColumn('evaluaciones', 'evaluador_id')) {
            try {
                Schema::table('evaluaciones', function (Blueprint $table) {
                    $table->dropForeign(['evaluador_id']);
                });
            } catch (\Throwable $e) {}
        }

        // Borrar dependientes y luego evaluadores
        if (Schema::hasTable('evaluador_tokens')) {
            Schema::drop('evaluador_tokens');
        }
        if (Schema::hasTable('evaluador_area')) {
            Schema::drop('evaluador_area');
        }
        if (Schema::hasTable('evaluadores')) {
            Schema::drop('evaluadores');
        }

        Schema::enableForeignKeyConstraints();
    }
};

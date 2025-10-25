<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscritos', function (Blueprint $table) {
            if (!Schema::hasColumn('inscritos', 'area_id')) {
                $table->foreignId('area_id')->nullable()->after('unidad')->constrained('areas')->nullOnDelete();
            }
            if (!Schema::hasColumn('inscritos', 'nivel_id')) {
                $table->foreignId('nivel_id')->nullable()->after('area_id')->constrained('niveles')->nullOnDelete();
            }
        });

        // Normaliza textos
        // Mapea por nombre (lower) → id
        // Nota: usa SQL puro para evitar dependencias de modelos en la migración.

        // ÁREAS
        DB::statement("
            UPDATE inscritos i
            SET area_id = a.id
            FROM areas a
            WHERE i.area_id IS NULL
              AND i.area IS NOT NULL
              AND LOWER(TRIM(i.area)) = LOWER(TRIM(a.nombre))
        ");

        // NIVELES
        DB::statement("
            UPDATE inscritos i
            SET nivel_id = n.id
            FROM niveles n
            WHERE i.nivel_id IS NULL
              AND i.nivel IS NOT NULL
              AND LOWER(TRIM(i.nivel)) = LOWER(TRIM(n.nombre))
        ");

        // (Opcional) Si quieres hacerlos obligatorios una vez mapeados:
        // Schema::table('inscritos', function (Blueprint $table) {
        //     $table->foreignId('area_id')->nullable(false)->change();
        //     $table->foreignId('nivel_id')->nullable(false)->change();
        // });
    }

    public function down(): void
    {
        Schema::table('inscritos', function (Blueprint $table) {
            if (Schema::hasColumn('inscritos', 'nivel_id')) {
                $table->dropConstrainedForeignId('nivel_id');
            }
            if (Schema::hasColumn('inscritos', 'area_id')) {
                $table->dropConstrainedForeignId('area_id');
            }
        });
    }
};

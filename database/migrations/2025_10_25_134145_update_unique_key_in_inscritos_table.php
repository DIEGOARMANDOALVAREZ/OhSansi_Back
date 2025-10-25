<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('inscritos', function (Blueprint $table) {
        // Eliminar unique actual
        $table->dropUnique('inscritos_documento_unique');

        // Agregar unique compuesto
        $table->unique(['documento', 'area', 'nivel'], 'inscritos_doc_area_nivel_unique');
    });
}

public function down()
{
    Schema::table('inscritos', function (Blueprint $table) {
        $table->dropUnique('inscritos_doc_area_nivel_unique');
        $table->unique('documento', 'inscritos_documento_unique');
    });
}

};

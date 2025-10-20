<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInscritosTable extends Migration
{
    public function up()
    {
        Schema::create('inscritos', function (Blueprint $table) {
            $table->id();
            $table->string('documento')->unique();  // Documento único
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('unidad');
            $table->string('area');
            $table->string('nivel');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('inscritos');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluador_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('evaluador_id');
            $table->string('name')->nullable();
            $table->string('token', 64)->unique(); // sha256
            $table->json('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('evaluador_id')->references('id')->on('evaluadores')->onDelete('cascade');
            $table->index(['evaluador_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluador_tokens');
    }
};

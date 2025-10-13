<?php
// database/migrations/2025_10_12_000000_create_usuarios_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nombres');
            $table->string('apellidos')->nullable();
            $table->string('correo')->unique();
            $table->string('telefono')->nullable();
            $table->string('ci')->unique();
            $table->string('password');
            $table->boolean('estado')->default(true);
            $table->rememberToken();
            $table->timestamp('creado_en')->nullable();
            $table->timestamp('actualizado_en')->nullable();
        });
    }
    public function down(): void {
        Schema::dropIfExists('usuarios');
    }
};

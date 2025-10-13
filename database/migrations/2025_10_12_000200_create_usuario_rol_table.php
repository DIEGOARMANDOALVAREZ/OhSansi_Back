<?php
// database/migrations/2025_10_12_000200_create_usuario_rol_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('usuario_rol', function (Blueprint $table) {
            $table->id();

            $table->foreignUuid('usuario_id')
                  ->constrained('usuarios')
                  ->cascadeOnUpdate()
                  ->cascadeOnDelete();

            $table->foreignId('rol_id')
                  ->constrained('roles')
                  ->cascadeOnUpdate()
                  ->restrictOnDelete();

            $table->timestamps();
            $table->unique(['usuario_id','rol_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('usuario_rol');
    }
};

<?php
// database/migrations/2025_10_12_000100_create_roles_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps(); // aquí puedes dejar created_at/updated_at estándar
        });
    }
    public function down(): void {
        Schema::dropIfExists('roles');
    }
};

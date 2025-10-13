<?php
// database/migrations/2025_10_10_000001_create_audits_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('audits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('entity_type', 80);   // e.g., 'Responsable'
            $t->unsignedBigInteger('entity_id');
            $t->string('action', 20);        // CREAR | EDITAR
            $t->json('changes')->nullable(); // {campo: [antes, despues]}
            $t->timestamps();
            $t->index(['entity_type','entity_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('audits'); }
};

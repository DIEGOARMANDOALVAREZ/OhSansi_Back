<?php
// database/migrations/2025_10_30_000002_create_final_snapshots_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('final_snapshots', function (Blueprint $t) {
            $t->id();
            $t->string('origen', 50);                 // p.ej. "FILTRO"
            $t->string('origen_hash', 64)->unique();  // hash del lote promovido
            $t->foreignId('responsable_id')->nullable()->constrained('responsables')->nullOnDelete();
            $t->json('payload');                      // { meta:{area_id?,nivel_id?}, total, ids:[...] }
            $t->timestamp('creado_at');               // sello de tiempo del snapshot
            // SIN $t->timestamps() para que el modelo pueda llevar $timestamps = false
        });
    }

    public function down(): void {
        Schema::dropIfExists('final_snapshots');
    }
};

<?php
// database/migrations/2025_10_22_000001_create_cierres_clasificacion_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('cierres_clasificacion', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('area_id')->nullable();
      $table->unsignedBigInteger('nivel_id')->nullable();
      $table->decimal('minima', 5, 2)->default(70.00);
      $table->unsignedBigInteger('responsable_id');
      $table->unsignedInteger('total_clasificados')->default(0);
      $table->unsignedInteger('total_no_clasificados')->default(0);
      $table->unsignedInteger('total_desclasificados')->default(0);
      $table->string('hash', 64);
      $table->timestamp('confirmado_at');
      $table->timestamps();

      $table->index(['area_id', 'nivel_id']);
    });
  }
  public function down(): void {
    Schema::dropIfExists('cierres_clasificacion');
  }
};

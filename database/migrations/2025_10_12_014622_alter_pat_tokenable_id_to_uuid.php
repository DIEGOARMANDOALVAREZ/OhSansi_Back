<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) (PG) Si existe el índice compuesto que Sanctum crea, lo bajamos
        DB::statement('DROP INDEX IF EXISTS personal_access_tokens_tokenable_type_tokenable_id_index');

        // 2) Cambiamos tokenable_id de BIGINT a UUID
        //    Nota: usamos ::text::uuid para permitir el CAST (la tabla suele estar vacía).
        DB::statement('ALTER TABLE personal_access_tokens
            ALTER COLUMN tokenable_id TYPE uuid USING (tokenable_id::text::uuid)');

        // 3) Volvemos a crear el índice compuesto
        DB::statement('CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index
            ON personal_access_tokens (tokenable_type, tokenable_id)');
    }

    public function down(): void
    {
        // ⚠️ Revertir a BIGINT no es seguro si ya hay UUIDs.
        // Si aún así necesitas bajar, esto los pone en NULL para no fallar.
        DB::statement('DROP INDEX IF EXISTS personal_access_tokens_tokenable_type_tokenable_id_index');

        DB::statement('ALTER TABLE personal_access_tokens
            ALTER COLUMN tokenable_id TYPE bigint USING NULL');

        DB::statement('CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index
            ON personal_access_tokens (tokenable_type, tokenable_id)');
    }
};

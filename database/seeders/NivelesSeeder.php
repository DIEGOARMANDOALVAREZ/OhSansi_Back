<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NivelesSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('niveles')->insert([
            ['nombre' => 'Nivel 1', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Nivel 2', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}

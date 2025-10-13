<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AreasSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('areas')->insert([
            ['nombre' => 'Ingenieria de Sistemas', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Ingenieria Civil',       'activo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}

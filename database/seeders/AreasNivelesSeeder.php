<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AreasNivelesSeeder extends Seeder {
    public function run(): void {
        DB::table('areas')->insert([
            ['nombre' => 'Sistemas', 'created_at'=>now(), 'updated_at'=>now()],
            ['nombre' => 'Electrónica', 'created_at'=>now(), 'updated_at'=>now()],
            ['nombre' => 'Diseño', 'created_at'=>now(), 'updated_at'=>now()],
        ]);
        DB::table('niveles')->insert([
            ['nombre' => 'Inicial', 'created_at'=>now(), 'updated_at'=>now()],
            ['nombre' => 'Intermedio', 'created_at'=>now(), 'updated_at'=>now()],
            ['nombre' => 'Avanzado', 'created_at'=>now(), 'updated_at'=>now()],
        ]);
    }
}


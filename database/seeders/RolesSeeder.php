<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        // Limpia la tabla roles antes de sembrar (opcional)
        DB::table('roles')->truncate();

        // Inserta roles base del sistema OH Sansi
        DB::table('roles')->insert([
            [
                'id'         => 1,
                'nombre'     => 'Administrador',
                'slug'       => Str::slug('administrador'),
                'activo'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id'         => 2,
                'nombre'     => 'Responsable',
                'slug'       => Str::slug('responsable'),
                'activo'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id'         => 3,
                'nombre'     => 'Evaluador',
                'slug'       => Str::slug('evaluador'),
                'activo'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->command->info('✅ Roles base insertados correctamente.');
    }
}

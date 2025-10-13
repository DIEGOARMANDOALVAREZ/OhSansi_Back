<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Usuario;
use App\Models\Rol;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Ejecutar primero los seeders base
        $this->call([
            RolesSeeder::class,
            AreasSeeder::class,
            NivelesSeeder::class,
            ResponsablesSeeder::class,
        ]);

        // === Crear usuarios base del sistema OH Sansi ===

        // Buscar roles existentes
        $adminRol = Rol::where('slug', 'administrador')->first();
        $respRol  = Rol::where('slug', 'responsable')->first();
        $evalRol  = Rol::where('slug', 'evaluador')->first();

        // 👑 Administrador general
        $admin = Usuario::updateOrCreate(
            ['correo' => 'admin@ohsansi.bo'],
            [
                'nombres'   => 'Administrador',
                'apellidos' => 'OH Sansi',
                'telefono'  => '+591 70000000',
                'ci'        => 'CI-0001',
                'password'  => 'admin123',   // el modelo lo hashea automáticamente
                'estado'    => true,
            ]
        );
        if ($adminRol) $admin->roles()->syncWithoutDetaching([$adminRol->id]);

        // 🧩 Responsable académico
        $responsable = Usuario::updateOrCreate(
            ['correo' => 'responsable@ohsansi.bo'],
            [
                'nombres'   => 'Ana',
                'apellidos' => 'Rojas',
                'telefono'  => '+591 71111111',
                'ci'        => 'CI-0002',
                'password'  => 'resp123',
                'estado'    => true,
            ]
        );
        if ($respRol) $responsable->roles()->syncWithoutDetaching([$respRol->id]);

        // 🧠 Evaluador
        $evaluador = Usuario::updateOrCreate(
            ['correo' => 'evaluador@ohsansi.bo'],
            [
                'nombres'   => 'Luis',
                'apellidos' => 'Heredia',
                'telefono'  => '+591 72222222',
                'ci'        => 'CI-0003',
                'password'  => 'eval123',
                'estado'    => true,
            ]
        );
        if ($evalRol) $evaluador->roles()->syncWithoutDetaching([$evalRol->id]);

        $this->command->info('✅ Usuarios base creados: admin, responsable, evaluador.');
    }
}

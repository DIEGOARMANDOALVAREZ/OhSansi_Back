<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Usuario;
use App\Models\Rol;
use Illuminate\Support\Facades\Hash;

class UsuariosSeeder extends Seeder {
  public function run(): void
  {
    $adminR = Rol::whereRaw('LOWER(slug) = ?', ['administrador'])->first();
    $respR  = Rol::whereRaw('LOWER(slug) = ?', ['responsable'])->first();
    $evalR  = Rol::whereRaw('LOWER(slug) = ?', ['evaluador'])->first();

    $admin = Usuario::create([
      'nombres'   => 'Administrador',
      'apellidos' => 'OH Sansi',
      'correo'    => 'admin@ohsansi.bo',
      'telefono'  => '+591 70000000',     // opcional
      'ci'        => 'CI-0001',           // ⬅️ OBLIGATORIO (no null)
      'password'  => Hash::make('admin123'),
      'estado'    => true,                 // tu col es boolean
    ]);
    if ($adminR) $admin->roles()->attach($adminR->id);

    $resp = Usuario::create([
      'nombres'   => 'Ana',
      'apellidos' => 'Rojas',
      'correo'    => 'responsable@sansi.edu',
      'telefono'  => '+591 71111111',     // opcional
      'ci'        => 'CI-0002',           // ⬅️ OBLIGATORIO
      'password'  => Hash::make('resp123'),
      'estado'    => true,
    ]);
    if ($respR) $resp->roles()->attach($respR->id);

    $eval = Usuario::create([
      'nombres'   => 'Luis',
      'apellidos' => 'Heredia',
      'correo'    => 'evaluador@sansi.edu',
      'telefono'  => '+591 72222222',     // opcional
      'ci'        => 'CI-0003',           // ⬅️ OBLIGATORIO
      'password'  => Hash::make('eval123'),
      'estado'    => true,
    ]);
    if ($evalR) $eval->roles()->attach($evalR->id);
  }
}

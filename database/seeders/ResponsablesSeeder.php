<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ResponsablesSeeder extends Seeder
{
    public function run(): void
    {
        $areaId  = DB::table('areas')->where('nombre', 'Ingenieria de Sistemas')->value('id');
        $nivelId = DB::table('niveles')->where('nombre', 'Nivel 1')->value('id');

        DB::table('responsables')->insert([
            'nombres'    => 'Juan',
            'apellidos'  => 'Perez',
            'ci'         => 'CB123456',
            'correo'     => 'juan.perez@ohsansi.bo',
            'telefono'   => '+591 700 00 00',
            'area_id'    => $areaId,
            'nivel_id'   => $nivelId, // o null si no usas niveles
            'activo'     => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluador extends Model
{
    use HasFactory;

    protected $table = 'evaluadores';

    // 👇 Asegúrate de incluir TODOS los campos que creas vía create()/update()
    protected $fillable = [
        'nombres',
        'apellidos',
        'correo',
        'telefono',
        'ci',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Relación many-to-many con Areas a través de la tabla pivote evaluador_area,
     * con campo adicional nivel_id y timestamps en la pivote.
     */
    public function asociaciones()
    {
        return $this->belongsToMany(Area::class, 'evaluador_area', 'evaluador_id', 'area_id')
            ->withPivot('nivel_id')
            ->withTimestamps();
    }
}

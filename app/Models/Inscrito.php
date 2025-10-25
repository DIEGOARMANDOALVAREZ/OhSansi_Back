<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inscrito extends Model
{
    use HasFactory;

    protected $fillable = [
        'documento',
        'nombres',
        'apellidos',
        'unidad',
        // textos originales
        'area',
        'nivel',
        // nuevos FK
        'area_id',
        'nivel_id',
    ];

    public function areaRef()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function nivelRef()
    {
        return $this->belongsTo(Nivel::class, 'nivel_id');
    }
}

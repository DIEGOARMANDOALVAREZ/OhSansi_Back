<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    use HasFactory;

    protected $table = 'areas';

    protected $fillable = [
        'nombre',
        // otros campos que tengas...
    ];

    public function evaluadores()
    {
        return $this->belongsToMany(Evaluador::class, 'evaluador_area', 'area_id', 'evaluador_id')
            ->withPivot('nivel_id')
            ->withTimestamps();
    }
}

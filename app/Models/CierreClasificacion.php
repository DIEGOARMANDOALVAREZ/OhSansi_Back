<?php
// app/Models/CierreClasificacion.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CierreClasificacion extends Model
{
    protected $table = 'cierres_clasificacion';

    protected $fillable = [
        'area_id','nivel_id','minima','responsable_id',
        'total_clasificados','total_no_clasificados','total_desclasificados',
        'hash','confirmado_at',
    ];

    protected $casts = [
        'minima' => 'float',
        'confirmado_at' => 'datetime',
    ];
}

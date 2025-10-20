<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evaluacion extends Model
{
    protected $table = 'evaluaciones';

    protected $fillable = [
        'inscrito_id',
        'evaluador_id',
        'area_id',
        'nivel_id',
        'notas',
        'nota_final',
        'concepto',
        'observaciones',
        'estado',
        'finalizado_at',
    ];

    protected $casts = [
        'notas' => 'array',
        'nota_final' => 'decimal:2',
        'finalizado_at' => 'datetime',
    ];

    public function inscrito()
    {
        return $this->belongsTo(Inscrito::class, 'inscrito_id');
    }

    public function evaluador()
    {
        return $this->belongsTo(Evaluador::class, 'evaluador_id');
    }
}

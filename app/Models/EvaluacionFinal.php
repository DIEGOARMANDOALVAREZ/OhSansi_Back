<?php
// app/Models/EvaluacionFinal.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluacionFinal extends Model
{
    protected $table = 'evaluaciones_finales';

    protected $fillable = [
        'finalista_id','evaluador_id','area_id','nivel_id',
        'notas','nota_final','concepto','estado','finalizado_at'
    ];

    protected $casts = [
        'notas' => 'array',
        'nota_final' => 'decimal:2',
        'finalizado_at' => 'datetime',
    ];

    public function finalista() { return $this->belongsTo(Finalista::class, 'finalista_id'); }
    public function evaluador() { return $this->belongsTo(Evaluador::class, 'evaluador_id'); }
    public function areaRef()   { return $this->belongsTo(Area::class, 'area_id'); }
    public function nivelRef()  { return $this->belongsTo(Nivel::class, 'nivel_id'); }
}

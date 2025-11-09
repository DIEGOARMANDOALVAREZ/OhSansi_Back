<?php
// app/Models/Finalista.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Finalista extends Model
{
    protected $table = 'finalistas';

    protected $fillable = [
        'inscrito_id',
        'area_id',
        'nivel_id',
        'cierre_id',
        'origen_hash',
        'habilitado_at',
    ];

    protected $casts = [
        'habilitado_at' => 'datetime',
    ];

    // La tabla no usa created_at / updated_at
    public $timestamps = false;

    // Relaciones
    public function inscrito()
    {
        return $this->belongsTo(Inscrito::class, 'inscrito_id');
    }

    public function areaRef()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function nivelRef()
    {
        return $this->belongsTo(Nivel::class, 'nivel_id');
    }

    public function snapshot()
    {
        return $this->belongsTo(FinalSnapshot::class, 'origen_hash', 'origen_hash');
    }
}

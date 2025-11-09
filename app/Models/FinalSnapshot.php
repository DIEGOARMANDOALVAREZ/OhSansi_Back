<?php
// app/Models/FinalSnapshot.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinalSnapshot extends Model
{
    protected $table = 'final_snapshots';

    protected $fillable = [
        'origen',
        'origen_hash',
        'responsable_id',
        'payload',
        'creado_at',
    ];

    protected $casts = [
        'payload'   => 'array',
        'creado_at' => 'datetime',
    ];

    // La tabla no usa created_at / updated_at
    public $timestamps = false;

    // Relaciones
    public function responsable()
    {
        return $this->belongsTo(Responsable::class, 'responsable_id');
    }

    public function finalistas()
    {
        return $this->hasMany(Finalista::class, 'origen_hash', 'origen_hash');
    }

    // Helper de resumen
    public function resumen(): array
    {
        return [
            'id'          => $this->id,
            'origen'      => $this->origen,
            'responsable' => trim((string)(optional($this->responsable)->nombres.' '.optional($this->responsable)->apellidos)),
            'total'       => $this->payload['total'] ?? 0,
            'meta'        => $this->payload['meta'] ?? [],
            'fecha'       => optional($this->creado_at)->format('Y-m-d H:i:s'),
        ];
    }
}

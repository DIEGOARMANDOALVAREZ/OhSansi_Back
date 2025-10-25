<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteChangeLog extends Model
{
    protected $table = 'note_change_logs';

    protected $fillable = [
        'occurred_at', 'user_id', 'competidor_id',
        'area_id', 'nivel_id', 'campo', 'anterior', 'nuevo', 'motivo',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    // Relaciones
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function competidor(): BelongsTo { return $this->belongsTo(Inscrito::class, 'competidor_id'); }
    public function area(): BelongsTo { return $this->belongsTo(Area::class); }
    public function nivel(): BelongsTo { return $this->belongsTo(Nivel::class); }
}

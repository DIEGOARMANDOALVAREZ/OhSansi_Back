<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvaluadorToken extends Model
{
    use HasFactory;

    protected $table = 'evaluador_tokens';

    protected $fillable = [
        'evaluador_id',
        'name',
        'token',      // sha256 del token plano
        'abilities',  // opcional (json)
    ];

    protected $casts = [
        'abilities' => 'array',
    ];

    public function evaluador()
    {
        return $this->belongsTo(Evaluador::class, 'evaluador_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Audit extends Model
{
    use HasFactory;

    protected $table = 'audits';

    protected $fillable = [
        'user_id', 'entity_type', 'entity_id', 'action', 'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public static function log($userId, string $type, int $id, string $action, $changes = null): void
    {
        self::create([
            'user_id'     => $userId,
            'entity_type' => $type,
            'entity_id'   => $id,
            'action'      => $action,
            'changes'     => $changes,
        ]);
    }
}

<?php
// app/Models/ResponsableToken.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ResponsableToken extends Model
{
    use HasFactory;

    protected $table = 'responsable_tokens';

    protected $fillable = ['responsable_id', 'name', 'token', 'abilities', 'expires_at'];

    protected $casts = [
        'abilities' => 'array',
        'expires_at' => 'datetime',
    ];

    public static function generateToken(int $responsableId, string $name = 'web', array $abilities = ['*']) {
        return self::create([
            'responsable_id' => $responsableId,
            'name'           => $name,
            'token'          => hash('sha256', Str::random(64)),
            'abilities'      => $abilities,
        ]);
    }

    public function responsable() {
        return $this->belongsTo(\App\Models\Responsable::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Usuario extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'usuarios';

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'nombres', 'apellidos', 'correo', 'telefono', 'ci', 'password', 'estado'
    ];

    protected $hidden = ['password'];

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    public function roles()
    {
        return $this->belongsToMany(
            Rol::class, 'usuario_rol', 'usuario_id', 'rol_id'
        );
    }

    public function tieneRol(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    public function getEmailAttribute()
    {
        return $this->correo;
    }

    // === Recomendado: autogenerar UUID y hashear password ===
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function setPasswordAttribute($value)
    {
        if ($value && !Str::startsWith($value, '$2y$')) {
            $this->attributes['password'] = bcrypt($value);
        } else {
            $this->attributes['password'] = $value;
        }
    }
}

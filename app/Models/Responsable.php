<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Responsable extends Model
{
    use HasFactory, HasApiTokens; // <-- Agregar HasApiTokens

    protected $table = 'responsables';

    protected $fillable = [
        'nombres', 'apellidos', 'ci', 'correo', 'telefono',
        'area_id', 'nivel_id', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // Relaciones
    public function area()  { return $this->belongsTo(Area::class); }
    public function nivel() { return $this->belongsTo(Nivel::class); }
}

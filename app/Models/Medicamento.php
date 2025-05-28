<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medicamento extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relaciones
    public function casoTratamientos(): HasMany
    {
        return $this->hasMany(CasoTratamiento::class);
    }

    public function resolucionTratamientos(): HasMany
    {
        return $this->hasMany(ResolucionTratamiento::class);
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->orderBy('nombre', 'asc');
    }

    // Mutators
    public function setNombreAttribute($value)
    {
        $this->attributes['nombre'] = strtoupper(trim($value));
    }
}

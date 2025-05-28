<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Variable extends Model
{
    use HasFactory;
    protected $fillable = ['parametro', 'caracteristica_id', 'manifestacion_id'];

    // Accesor para guardar en mayúsculas
    public function setParametroAttribute($value)
    {
        $this->attributes['parametro'] = strtoupper($value);
    }

    // Relación con manifestación
    public function manifestacion()
    {
        return $this->belongsTo(Manifestacion::class);
    }

    // Relación con característica
    public function caracteristica()
    {
        return $this->belongsTo(Caracteristica::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Materia extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
    ];

    /**
     * Relación 1:N con grupos
     * Una materia puede estar asociada a varios grupos
     */
    public function grupos()
    {
        return $this->hasMany(Grupo::class);
    }

    /**
     * Relación 1:N con casos
     * Una materia puede tener múltiples casos asociados
     */
    public function casos()
    {
        return $this->hasMany(Caso::class);
    }
}

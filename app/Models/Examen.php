<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Examen extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
    ];

    /**
     * Relación 1:N con ExamenComplementarios
     */
    public function examenComplementarios()
    {
        return $this->hasMany(ExamenComplementario::class);
    }

    /**
     * Relación 1:N con ResolucionExamen
     */
    public function resolucionExamenes()
    {
        return $this->hasMany(ResolucionExamen::class);
    }
}

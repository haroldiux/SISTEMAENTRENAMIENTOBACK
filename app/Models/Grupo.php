<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'materia_id',
        'docente_id',
        'gestion_id'
    ];

    /**
     * Obtiene la materia asociada a este grupo
     */
    public function materia()
    {
        return $this->belongsTo(Materia::class);
    }

    /**
     * Obtiene el docente asociado a este grupo
     */
    public function docente()
    {
        return $this->belongsTo(Docente::class);
    }

    /**
     * Obtiene la gestión asociada a este grupo
     */
    public function gestion()
    {
        return $this->belongsTo(Gestion::class);
    }

    /**
     * Obtiene los estudiantes inscritos en este grupo
     */
    public function estudiantes()
    {
        return $this->belongsToMany(Estudiante::class, 'inscripcions', 'grupo_id', 'estudiante_id')
                    ->withTimestamps();
    }
}

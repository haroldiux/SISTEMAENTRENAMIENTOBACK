<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluacion extends Model
{
    use HasFactory;

    protected $table = 'evaluaciones';

    protected $fillable = [
        'titulo',
        'fecha_inicio',
        'fecha_fin',
        'filtro_nivel_dificultad',
        'modo_seleccion',
        'limite_casos',
        'materia_id',
        'grupo_id',
        'gestion_id',
        'docente_id'
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime'
    ];

    public function materia()
    {
        return $this->belongsTo(Materia::class);
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class);
    }

    public function gestion()
    {
        return $this->belongsTo(Gestion::class);
    }

    public function docente()
    {
        return $this->belongsTo(Docente::class);
    }

    public function evaluacionCasos()
    {
        return $this->hasMany(EvaluacionCaso::class);
    }

    // Método de conveniencia para acceder a los casos
    public function casos()
    {
        return $this->belongsToMany(Caso::class, 'evaluacion_casos')
                    ->withTimestamps();
    }

    // Método para obtener los estudiantes asignados a esta evaluación
    public function estudiantes()
    {
        return $this->hasManyThrough(
            EvaluacionEstudiante::class,
            EvaluacionCaso::class,
            'evaluacion_id', // Clave foránea en evaluacion_casos
            'evaluacion_caso_id', // Clave foránea en evaluacion_estudiantes
            'id', // Clave local en evaluaciones
            'id' // Clave local en evaluacion_casos
        );
    }
}

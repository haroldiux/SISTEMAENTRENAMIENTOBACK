<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvaluacionEstudiante extends Model
{
    use HasFactory;

    protected $table = 'evaluacion_estudiantes';

    protected $fillable = [
        'evaluacion_caso_id',
        'estudiante_id',
        'completado'
    ];

    protected $casts = [
        'completado' => 'boolean'
    ];

    public function evaluacionCaso()
    {
        return $this->belongsTo(EvaluacionCaso::class);
    }

    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class);
    }

    // Métodos de conveniencia para acceder directamente a la evaluación y al caso
    public function evaluacion()
    {
        return $this->evaluacionCaso->evaluacion();
    }

    public function caso()
    {
        return $this->evaluacionCaso->caso();
    }

    // Método para comprobar si existe una resolución para esta asignación
    public function tieneResolucion()
    {
        return Resolucion::where('estudiante_id', $this->estudiante_id)
            ->where('caso_id', $this->evaluacionCaso->caso_id)
            ->exists();
    }

    // Método para obtener la resolución asociada (si existe)
    public function resolucion()
    {
        return Resolucion::where('estudiante_id', $this->estudiante_id)
            ->where('caso_id', $this->evaluacionCaso->caso_id)
            ->first();
    }
}

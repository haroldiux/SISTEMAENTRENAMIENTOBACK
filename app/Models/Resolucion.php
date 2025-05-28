<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Resolucion extends Model
{
    use HasFactory;

    protected $fillable = [
        'estudiante_id',
        'caso_id',
        'gestion_id',
        'tipo',
        'puntaje',
        'fecha_resolucion',
    ];

    /**
     * Relación N:1 con Estudiante
     */
    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class);
    }

    /**
     * Relación N:1 con Caso
     */
    public function caso()
    {
        return $this->belongsTo(Caso::class);
    }

    /**
     * Relación N:1 con Gestión
     */
    public function gestion()
    {
        return $this->belongsTo(Gestion::class);
    }

    /**
     * Relación 1:N con resolución_motivos
     */
    public function motivos()
    {
        return $this->hasMany(ResolucionMotivo::class);
    }

    /**
     * Relación 1:N con resolución_examens
     */
    public function examenes()
    {
        return $this->hasMany(ResolucionExamen::class);
    }

    /**
     * Relación 1:N con resolución_manifestacions
     */
    public function manifestaciones()
    {
        return $this->hasMany(ResolucionManifestacion::class);
    }

    public function resolucionTratamientos()
    {
        return $this->hasMany(ResolucionTratamiento::class);
    }

    // Accessors
    public function getPuntajeTextoAttribute(): string
    {
        if ($this->puntaje >= 90) return 'Excelente';
        if ($this->puntaje >= 80) return 'Muy Bueno';
        if ($this->puntaje >= 70) return 'Bueno';
        if ($this->puntaje >= 60) return 'Regular';
        return 'Deficiente';
    }

    public function getEstadoAttribute(): string
    {
        return $this->puntaje >= 60 ? 'Aprobado' : 'Reprobado';
    }

    // Scopes
    public function scopeAprobadas($query)
    {
        return $query->where('puntaje', '>=', 60);
    }

    public function scopeReprobadas($query)
    {
        return $query->where('puntaje', '<', 60);
    }

    public function scopePorEstudiante($query, $estudianteId)
    {
        return $query->where('estudiante_id', $estudianteId);
    }

    public function scopePorCaso($query, $casoId)
    {
        return $query->where('caso_id', $casoId);
    }

    public function scopePorGestion($query, $gestionId)
    {
        return $query->where('gestion_id', $gestionId);
    }

    // Métodos auxiliares
    public function calcularPorcentajeTratamientos(): float
    {
        $totalTratamientos = $this->resolucionTratamientos()->count();
        if ($totalTratamientos === 0) return 0;

        $tratamientosCorrectos = $this->resolucionTratamientos()->correctas()->count();
        return ($tratamientosCorrectos / $totalTratamientos) * 100;
    }

    public function obtenerResumenResultados(): array
    {
        return [
            'motivos' => [
                'total' => $this->resolucionMotivos()->count(),
                'correctos' => $this->resolucionMotivos()->where('respuesta', true)->count(),
            ],
            'examenes' => [
                'total' => $this->resolucionExamenes()->count(),
                'correctos' => $this->resolucionExamenes()->where('respuesta', true)->count(),
            ],
            'manifestaciones' => [
                'total' => $this->resolucionManifestaciones()->count(),
                'correctas' => $this->resolucionManifestaciones()->where('respuesta', true)->count(),
            ],
            'tratamientos' => [
                'total' => $this->resolucionTratamientos()->count(),
                'correctos' => $this->resolucionTratamientos()->correctas()->count(),
                'porcentaje' => $this->calcularPorcentajeTratamientos(),
            ]
        ];
    }
}

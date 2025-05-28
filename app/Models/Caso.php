<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Caso extends Model
{
    use HasFactory;

    protected $fillable = [
        'titulo',
        'nivel_dificultad',
        'enunciado',
        'diagnostico',
        'materia_id',
        'docente_id',
    ];

    /**
     * Relación N:1 con Materia
     */
    public function materia()
    {
        return $this->belongsTo(Materia::class);
    }

    /**
     * Relación N:1 con Docente
     */
    public function docente()
    {
        return $this->belongsTo(Docente::class);
    }

    /**
     * Relación 1:N con Resoluciones
     */
    public function resoluciones()
    {
        return $this->hasMany(Resolucion::class);
    }

    /**
     * Relación 1:N con Configuraciones
     */
    public function configuraciones()
    {
        return $this->hasMany(Configuracion::class);
    }

    /**
     * Relación 1:N con ExamenComplementario
     */
    public function examenComplementarios()
    {
        return $this->hasMany(ExamenComplementario::class);
    }

    /**
     * Relación 1:N con CasoMotivo
     */
    public function casoMotivos()
    {
        return $this->hasMany(CasoMotivo::class);
    }

    /**
     * Relación 1:N con Presentas
     */
    public function presentas()
    {
        return $this->hasMany(Presenta::class);
    }
// Nueva relación para tratamientos
    public function casoTratamientos()
    {
        return $this->hasMany(CasoTratamiento::class);
    }

    // Accessors
    public function getNivelDificultadTextoAttribute(): string
    {
        return match($this->nivel_dificultad) {
            0 => 'Básico',
            1 => 'Intermedio',
            2 => 'Avanzado',
            default => 'No definido'
        };
    }

    public function getTieneDiagnosticoAttribute(): bool
    {
        return !empty($this->diagnostico);
    }

    public function getTieneTratamientosAttribute(): bool
    {
        return $this->casoTratamientos()->count() > 0;
    }

    // Scopes
    public function scopePorNivel($query, $nivel)
    {
        return $query->where('nivel_dificultad', $nivel);
    }

    public function scopePorMateria($query, $materiaId)
    {
        return $query->where('materia_id', $materiaId);
    }

    public function scopePorDocente($query, $docenteId)
    {
        return $query->where('docente_id', $docenteId);
    }

    public function scopeConTratamientos($query)
    {
        return $query->whereHas('casoTratamientos');
    }

    public function scopeConDiagnostico($query)
    {
        return $query->whereNotNull('diagnostico');
    }

    // Métodos auxiliares
    public function getTratamientosCompletos()
    {
        return $this->casoTratamientos()->with([
            'medicamento',
            'dosis',
            'frecuencia',
            'duracion'
        ])->get();
    }

    public function clonar()
    {
        $nuevoCaso = $this->replicate();
        $nuevoCaso->titulo = $this->titulo . ' (Copia)';
        $nuevoCaso->save();

        // Copiar relaciones
        foreach ($this->configuraciones as $config) {
            $nuevaConfig = $config->replicate();
            $nuevaConfig->caso_id = $nuevoCaso->id;
            $nuevaConfig->save();
        }

        foreach ($this->casoMotivos as $motivo) {
            $nuevoMotivo = $motivo->replicate();
            $nuevoMotivo->caso_id = $nuevoCaso->id;
            $nuevoMotivo->save();
        }

        foreach ($this->presentas as $presenta) {
            $nuevoPresenta = $presenta->replicate();
            $nuevoPresenta->caso_id = $nuevoCaso->id;
            $nuevoPresenta->save();
        }

        foreach ($this->casoTratamientos as $tratamiento) {
            $nuevoTratamiento = $tratamiento->replicate();
            $nuevoTratamiento->caso_id = $nuevoCaso->id;
            $nuevoTratamiento->save();
        }

        return $nuevoCaso;
    }
}

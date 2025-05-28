<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResolucionTratamiento extends Model
{
    use HasFactory;

    protected $fillable = [
        'resolucion_id',
        'medicamento_id',
        'dosis_id',
        'frecuencia_id',
        'duracion_id',
        'respuesta',
        'observaciones'
    ];

    protected $casts = [
        'respuesta' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relaciones
    public function resolucion(): BelongsTo
    {
        return $this->belongsTo(Resolucion::class);
    }

    public function medicamento(): BelongsTo
    {
        return $this->belongsTo(Medicamento::class);
    }

    public function dosis(): BelongsTo
    {
        return $this->belongsTo(Dosis::class);
    }

    public function frecuencia(): BelongsTo
    {
        return $this->belongsTo(Frecuencia::class);
    }

    public function duracion(): BelongsTo
    {
        return $this->belongsTo(Duracion::class);
    }

    // Accessors
    public function getTratamientoCompletoAttribute(): string
    {
        return sprintf(
            '%s %s %s por %s',
            $this->medicamento->nombre ?? '',
            $this->dosis->descripcion ?? '',
            $this->frecuencia->descripcion ?? '',
            $this->duracion->descripcion ?? ''
        );
    }

    public function getEstadoAttribute(): string
    {
        return $this->respuesta ? 'Correcto' : 'Incorrecto';
    }

    // Scopes
    public function scopeCorrectas($query)
    {
        return $query->where('respuesta', true);
    }

    public function scopeIncorrectas($query)
    {
        return $query->where('respuesta', false);
    }
}

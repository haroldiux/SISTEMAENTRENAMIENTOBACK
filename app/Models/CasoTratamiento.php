<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CasoTratamiento extends Model
{
    use HasFactory;

    protected $fillable = [
        'caso_id',
        'medicamento_id',
        'dosis_id',
        'frecuencia_id',
        'duracion_id',
        'observaciones'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relaciones
    public function caso(): BelongsTo
    {
        return $this->belongsTo(Caso::class);
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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResolucionExamen extends Model
{
    use HasFactory;

    protected $table = 'resolucion_examens';

    protected $fillable = [
        'resolucion_id',
        'examen_id',
    ];

    /**
     * Relación N:1 con Resolucion
     */
    public function resolucion()
    {
        return $this->belongsTo(Resolucion::class);
    }

    /**
     * Relación N:1 con Examen
     */
    public function examen()
    {
        return $this->belongsTo(Examen::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResolucionMotivo extends Model
{
    use HasFactory;

    protected $table = 'resolucion_motivos';

    protected $fillable = [
        'resolucion_id',
        'motivo_id',
    ];

    /**
     * Relación N:1 con Resolucion
     */
    public function resolucion()
    {
        return $this->belongsTo(Resolucion::class);
    }

    /**
     * Relación N:1 con Motivo
     */
    public function motivo()
    {
        return $this->belongsTo(Motivo::class);
    }
}

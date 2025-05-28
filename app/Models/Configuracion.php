<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    use HasFactory;

    protected $fillable = [
        'tiempo_resolucion',
        'tiempo_vista_enunciado',
        'tipo',
        'caso_id',
    ];

    /**
     * Relación N:1 con Caso
     */
    public function caso()
    {
        return $this->belongsTo(Caso::class);
    }
}

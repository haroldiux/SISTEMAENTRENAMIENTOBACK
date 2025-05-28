<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamenComplementario extends Model
{
    use HasFactory;

    protected $table = 'examen_complementarios';

    protected $fillable = [
        'caso_id',
        'examen_id',
        'archivo',
    ];

    /**
     * Relación N:1 con Caso
     */
    public function caso()
    {
        return $this->belongsTo(Caso::class);
    }

    /**
     * Relación N:1 con Examen
     */
    public function examen()
    {
        return $this->belongsTo(Examen::class);
    }
}

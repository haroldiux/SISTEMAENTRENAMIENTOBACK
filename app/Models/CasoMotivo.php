<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CasoMotivo extends Model
{
    use HasFactory;

    protected $table = 'caso_motivos';

    protected $fillable = [
        'caso_id',
        'motivo_id',
    ];

    /**
     * Relación N:1 con Caso
     */
    public function caso()
    {
        return $this->belongsTo(Caso::class);
    }

    /**
     * Relación N:1 con Motivo
     */
    public function motivo()
    {
        return $this->belongsTo(Motivo::class);
    }
}

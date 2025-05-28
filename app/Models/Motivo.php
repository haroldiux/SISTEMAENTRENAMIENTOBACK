<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Motivo extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
    ];

    /**
     * Relación 1:N con CasoMotivo
     */
    public function casoMotivos()
    {
        return $this->hasMany(CasoMotivo::class);
    }

    /**
     * Relación 1:N con ResolucionMotivo
     */
    public function resolucionMotivos()
    {
        return $this->hasMany(ResolucionMotivo::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResolucionManifestacion extends Model
{
    use HasFactory;

    protected $table = 'resolucion_manifestacions';

    protected $fillable = [
        'resolucion_id',
        'manifestacion_id',
        'localizacion_id',
        'variable_id',
    ];

    /**
     * Relación N:1 con Resolucion
     */
    public function resolucion()
    {
        return $this->belongsTo(Resolucion::class);
    }

    /**
     * Relación N:1 con Manifestacion
     */
    public function manifestacion()
    {
        return $this->belongsTo(Manifestacion::class);
    }

    /**
     * Relación N:1 con Localizacion
     */
    public function localizacion()
    {
        return $this->belongsTo(Localizacion::class);
    }

    /**
     * Relación N:1 con Variable
     */
    public function variable()
    {
        return $this->belongsTo(Variable::class);
    }
}

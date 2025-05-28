<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Manifestacion extends Model
{
    use HasFactory;

    protected $table = 'manifestacions';

    protected $fillable = [
        'nombre',
        'tipo',
    ];

    /**
     * Relación 1:N con presentas
     */
    public function presentas()
    {
        return $this->hasMany(Presenta::class);
    }

    /**
     * Relación 1:N con variables
     */
    public function variables()
    {
        return $this->hasMany(Variable::class);
    }

    /**
     * Relación 1:N con resolución de manifestaciones
     */
    public function resolucionManifestaciones()
    {
        return $this->hasMany(ResolucionManifestacion::class);
    }
}

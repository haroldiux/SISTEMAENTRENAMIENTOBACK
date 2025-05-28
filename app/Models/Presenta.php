<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Presenta extends Model
{
    use HasFactory;

    protected $table = 'presentas';

    protected $fillable = [
        'caso_id',
        'manifestacion_id',
        'variable_id',
        'localizacion_id',
    ];

    /**
     * Relación N:1 con Caso
     */
    public function caso()
    {
        return $this->belongsTo(Caso::class);
    }

    /**
     * Relación N:1 con Manifestacion
     */
    public function manifestacion()
    {
        return $this->belongsTo(Manifestacion::class);
    }

    /**
     * Relación N:1 con Variable
     */
    public function variable()
    {
        return $this->belongsTo(Variable::class);
    }

    /**
     * Relación N:1 con Localizacion
     */
    public function localizacion()
    {
        return $this->belongsTo(Localizacion::class);
    }
}

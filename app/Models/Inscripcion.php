<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inscripcion extends Model
{
    use HasFactory;

    protected $table = 'inscripcions';

    protected $fillable = [
        'estudiante_id',
        'grupo_id'
    ];

    /**
     * Obtiene el estudiante asociado a esta inscripción
     */
    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class);
    }

    /**
     * Obtiene el grupo asociado a esta inscripción
     */
    public function grupo()
    {
        return $this->belongsTo(Grupo::class);
    }
}

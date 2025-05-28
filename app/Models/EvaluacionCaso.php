<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvaluacionCaso extends Model
{
    use HasFactory;

    protected $table = 'evaluacion_casos';

    protected $fillable = [
        'evaluacion_id',
        'caso_id'
    ];

    public function evaluacion()
    {
        return $this->belongsTo(Evaluacion::class);
    }

    public function caso()
    {
        return $this->belongsTo(Caso::class);
    }

    public function asignacionesEstudiantes()
    {
        return $this->hasMany(EvaluacionEstudiante::class);
    }
}

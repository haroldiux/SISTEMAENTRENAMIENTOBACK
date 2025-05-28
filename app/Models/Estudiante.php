<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estudiante extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombres',
        'apellido1',
        'apellido2',
        'correo',
        'telefono',
        'estado',
        'user_id',
    ];

    /**
     * Relación N:1 con User
     * Un estudiante pertenece a un usuario
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación 1:N con Inscripciones
     * Un estudiante puede tener varias inscripciones
     */
    public function inscripciones()
    {
        return $this->hasMany(Inscripcion::class);
    }

    /**
     * Relación 1:N con Resoluciones
     * Un estudiante puede tener varias resoluciones
     */
    public function resoluciones()
    {
        return $this->hasMany(Resolucion::class);
    }
}

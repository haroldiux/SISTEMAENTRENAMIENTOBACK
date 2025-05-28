<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Docente extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombres',
        'apellidos',
        'correo',
        'telefono',
        'estado',
        'user_id',
    ];

    /**
     * Relación N:1 con User
     * Un docente pertenece a un usuario
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación 1:N con Grupos
     * Un docente puede estar a cargo de varios grupos
     */
    public function grupos()
    {
        return $this->hasMany(Grupo::class);
    }

    /**
     * Relación 1:N con Casos
     * Un docente puede estar asociado a varios casos
     */
    public function casos()
    {
        return $this->hasMany(Caso::class);
    }
}

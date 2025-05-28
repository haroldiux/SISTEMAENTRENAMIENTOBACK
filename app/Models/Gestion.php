<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gestion extends Model
{
    use HasFactory;

    protected $table = 'gestions';

    protected $fillable = [
        'nombre',
        'anio',
        'estado'
    ];

    /**
     * Obtiene los grupos asociados a esta gestión
     */
    public function grupos()
    {
        return $this->hasMany(Grupo::class);
    }

    /**
     * Obtiene las resoluciones asociadas a esta gestión
     */
    public function resoluciones()
    {
        return $this->hasMany(Resolucion::class);
    }

    /**
     * Obtiene la gestión activa
     */
    public static function getActiva()
    {
        return self::where('estado', 1)->first();
    }
}

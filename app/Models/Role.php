<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
    ];

    /**
     * Relación 1:N – Un rol puede tener muchos usuarios
     */
    public function users()
    {
        return $this->hasMany(User::class, 'rol_id');
    }
}

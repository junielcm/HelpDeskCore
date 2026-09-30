<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo de catálogo de roles.
 *
 * Define los cuatro niveles de acceso del sistema: client, agent, supervisor
 * y admin. Se mantiene en la base de datos (tabla roles) para poder ampliar
 * el catálogo sin tocar código.
 */
class Role extends Model
{
    protected $table = 'roles';

    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Usuarios que tienen asignado este rol.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

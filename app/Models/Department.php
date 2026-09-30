<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo de catálogo de departamentos.
 *
 * Agrupa los equipos de atención (p. ej. Soporte Técnico). El staff se asigna
 * a un departamento y los tickets se rutean hacia él; los agentes solo ven
 * los tickets de su propio departamento.
 */
class Department extends Model
{
    protected $table = 'departments';

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    /**
     * Usuarios (personal) asignados a este departamento.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Tickets ruteados a este departamento.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}

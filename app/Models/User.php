<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // Trait de Sanctum: habilita los tokens de acceso por API

/**
 * Modelo del usuario del sistema.
 *
 * Representa tanto a los solicitantes de soporte (client) como al personal
 * (agent, supervisor, admin). Cada usuario pertenece a un rol y, en el caso
 * del personal, a un departamento. Al heredar de Authenticatable, la clase
 * expone la autenticación basada en sesión y, con Sanctum, los tokens del API.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'role_id',
        'department_id',
        'name',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Rol del usuario (client, agent, supervisor o admin).
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Departamento al que pertenece el usuario (personal de soporte).
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Calificaciones emitidas por el usuario (normalmente clientes).
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(TicketRating::class);
    }
}

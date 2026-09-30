<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo principal de la aplicación: la solicitud de soporte.
 *
 * Un ticket es abierto por un cliente, se enruta a un departamento y puede
 * asignarse a un agente concreto. Mantiene la trazabilidad completa a través
 * de comentarios, adjuntos y la bitácora de auditoría. El borrado es lógico
 * (SoftDeletes) para conservar el historial.
 */
class Ticket extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'status',
        'priority',
        'client_id',
        'department_id',
        'assigned_to',
        'sla_violated',
        'first_response_at',
    ];

    /**
     * Los campos SLA se serializan como boolean / fecha según corresponda.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sla_violated' => 'boolean',
        'first_response_at' => 'datetime',
    ];

    /**
     * Cliente que abrió la solicitud.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Departamento al que se enruta el ticket.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Agente asignado para resolver el ticket (puede ser nulo).
     */
    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Conversación pública (e interna) del ticket.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Bitácora de auditoría: cambios de estado, reasignaciones y notas.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(TicketAuditLog::class);
    }

    /**
     * Archivos adjuntos del ticket (y de sus comentarios).
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    /**
     * Calificaciones de satisfacción recibidas para el ticket.
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(TicketRating::class);
    }
}

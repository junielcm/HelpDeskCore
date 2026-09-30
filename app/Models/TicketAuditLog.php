<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de la bitácora de auditoría de tickets.
 *
 * Registra cada evento relevante de un ticket (creación, cambio de estado,
 * reasignación, edición de contenidos y notas internas) con el valor anterior
 * y posterior. Es un historial de solo lectura pensado para trazabilidad.
 */
class TicketAuditLog extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'action',
        'old_value',
        'new_value',
    ];

    /**
     * Ticket sobre el que se registra el evento.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Usuario que ejecutó la acción.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

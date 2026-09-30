<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de calificación de satisfacción.
 *
 * Un cliente puntúa el ticket una vez resuelto con una nota de 1 a 5 y, de
 * forma opcional, deja un comentario de feedback. Es la fuente de datos del
 * indicador de satisfacción del panel de gestión.
 */
class TicketRating extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'rating',
        'feedback',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

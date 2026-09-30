<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo de comentario dentro de un ticket.
 *
 * Cada comentario pertenece a un ticket y a un autor. El campo is_internal
 * diferencia las respuestas públicas (visibles para el cliente) de las notas
 * internas del staff, que quedan fuera de la vista del solicitante.
 */
class Comment extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'body',
        'is_internal',
    ];

    /**
     * Ticket al que pertenece el comentario.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Autor del comentario.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Archivos adjuntos del comentario.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }
}

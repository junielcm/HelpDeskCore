<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de archivo adjunto.
 *
 * Un adjunto puede pertenecer a un ticket o a un comentario concreto; por eso
 * comment_id es opcional. Guarda la ruta física en el disco público además de
 * el nombre y tamaño originales para mostrar esa información en la interfaz.
 */
class TicketAttachment extends Model
{
    protected $fillable = [
        'ticket_id',
        'comment_id',
        'file_path',
        'file_name',
        'file_size',
    ];

    /**
     * Ticket al que se adjunta el archivo.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Comentario (opcional) al que se adjunta el archivo.
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }
}

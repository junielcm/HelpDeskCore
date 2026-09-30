<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifica al cliente que el estado de su ticket cambió (incluida la
 * resolución), manteniéndolo al tanto del avance de su solicitud.
 */
class TicketStatusChanged extends Notification
{
    use Queueable;

    public function __construct(public Ticket $ticket) {}

    /**
     * Solo se persiste en la tabla de notificaciones (database channel).
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_title' => $this->ticket->title,
            'status' => $this->ticket->status,
        ];
    }
}

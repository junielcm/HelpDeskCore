<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifica al agente que se le asignó un ticket.
 */
class TicketAssigned extends Notification
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
            'client_name' => $this->ticket->client?->name,
            'priority' => $this->ticket->priority,
        ];
    }
}

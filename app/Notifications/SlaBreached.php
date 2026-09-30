<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Alerta de incumplimiento de SLA.
 *
 * Se envía automáticamente cuando el comando programado detecta que un ticket
 * abierto excedió el tiempo máximo de resolución (o de primera respuesta)
 * configurado para su prioridad. Llega al supervisor del departamento y al
 * agente asignado, si lo hubiera, para que actúen de inmediato.
 */
class SlaBreached extends Notification
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
            'priority' => $this->ticket->priority,
            'sla_violated' => $this->ticket->sla_violated,
        ];
    }
}

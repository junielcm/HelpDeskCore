<?php

namespace App\Observers;

use App\Models\Ticket;
use App\Models\TicketAuditLog;
use App\Models\User;
use App\Notifications\TicketAssigned;
use App\Notifications\TicketCreated;
use App\Notifications\TicketStatusChanged;
use Illuminate\Support\Facades\Notification;

/**
 * Observador del modelo Ticket.
 *
 * Centraliza la escritura de la bitácora de auditoría y el envío de
 * notificaciones: en lugar de esparcir esa lógica por los controladores, este
 * observador reacciona a los eventos del ciclo de vida del ticket (created /
 * updated) y documenta qué cambió, quién lo cambió, y a quién avisar.
 */
class TicketObserver
{
    /**
     * Registra la creación de un ticket y avisa al personal del departamento.
     */
    public function created(Ticket $ticket): void
    {
        $this->log($ticket, 'created', null, $ticket->status);

        $this->notifyDepartment($ticket);
    }

    /**
     * Detecta y audita cambios de estado, reasignación y ediciones del ticket.
     *
     * En un solo método se comparan los valores originales con los nuevos:
     * - status_changed cuando cambia el estado;
     * - reassigned cuando cambia el agente asignado;
     * - ticket_edited ante cualquier otro campo modificado.
     */
    public function updated(Ticket $ticket): void
    {
        if ($ticket->wasChanged('status')) {
            $this->log(
                $ticket,
                'status_changed',
                $ticket->getOriginal('status'),
                $ticket->status
            );

            $this->notifyClientStatusChange($ticket);
        }

        if ($ticket->wasChanged('assigned_to')) {
            $this->log(
                $ticket,
                'reassigned',
                $ticket->getOriginal('assigned_to'),
                $ticket->assigned_to
            );

            $this->notifyAssignedAgent($ticket);
        }

        // Cualquier otro cambio (título, descripción, prioridad, etc.) se
        // agrupa como edición general del ticket.
        $editedFields = array_diff(
            array_keys($ticket->getChanges()),
            ['status', 'assigned_to', 'updated_at']
        );

        if (! empty($editedFields)) {
            $this->log($ticket, 'ticket_edited', null, json_encode($ticket->getChanges()));
        }
    }

    /**
     * Guarda la auditoría registrando al usuario autenticado.
     *
     * Si no hay usuario autenticado (por ejemplo en procesos del sistema)
     * se omite el registro para no contaminar la bitácora con entradas sin
     * responsable.
     */
    private function log(Ticket $ticket, string $action, ?string $oldValue, ?string $newValue): void
    {
        $userId = auth()->id();

        if (! $userId) {
            return;
        }

        TicketAuditLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => $userId,
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);
    }

    /**
     * Avisa al personal (agentes y supervisor) del departamento del ticket
     * cuando este se crea, para que lo tomen de la cola.
     */
    private function notifyDepartment(Ticket $ticket): void
    {
        $staff = User::query()
            ->where('department_id', $ticket->department_id)
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('name', ['agent', 'supervisor']))
            ->get();

        Notification::send($staff, new TicketCreated($ticket));
    }

    /**
     * Avisa al cliente cuando cambia el estado de su ticket.
     */
    private function notifyClientStatusChange(Ticket $ticket): void
    {
        if (! $ticket->client_id) {
            return;
        }

        $client = User::find($ticket->client_id);

        if ($client) {
            $client->notify(new TicketStatusChanged($ticket));
        }
    }

    /**
     * Avisa al agente recién asignado al ticket.
     */
    private function notifyAssignedAgent(Ticket $ticket): void
    {
        if (! $ticket->assigned_to) {
            return;
        }

        $agent = User::find($ticket->assigned_to);

        if ($agent) {
            $agent->notify(new TicketAssigned($ticket));
        }
    }
}

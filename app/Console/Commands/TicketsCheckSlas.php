<?php

namespace App\Console\Commands;

use App\Models\SlaRule;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\SlaBreached;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

#[Signature('tickets:check-slas')]
#[Description('Detecta y escala tickets que incumplieron el SLA según su prioridad')]
class TicketsCheckSlas extends Command
{
    /**
     * Orden de escalamiento de prioridad cuando un ticket incumple el SLA.
     *
     * Cada prioridad escala a la inmediatamente superior (la última se queda
     * en 'urgent'). El mapa indica hacia dónde se sube cada prioridad.
     *
     * @var array<string, string>
     */
    private const ESCALATION = [
        'low' => 'medium',
        'medium' => 'high',
        'high' => 'urgent',
        'urgent' => 'urgent',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $rules = SlaRule::keyedByPriority();

        $tickets = Ticket::query()
            ->whereIn('status', ['open', 'in_progress'])
            ->where('sla_violated', false)
            ->with(['assignedAgent', 'department'])
            ->get();

        $breached = 0;
        $escalated = 0;

        foreach ($tickets as $ticket) {
            if (! $this->isBreached($ticket, $rules)) {
                continue;
            }

            $breached++;
            $escalated += $this->escalate($ticket);

            $this->notify($ticket);
        }

        $this->info("SLA evaluado: {$tickets->count()} tickets abiertos. {$breached} incumplimientos, {$escalated} prioridades escaladas.");

        return Command::SUCCESS;
    }

    /**
     * Determina si un ticket superó el umbral de respuesta o de resolución.
     *
     * - Respuesta: aún no hay ninguna respuesta del staff (first_response_at
     *   nulo) y ha pasado más del tiempo de respuesta desde la creación.
     * - Resolución: sigue abierto/en proceso y ha pasado más del tiempo de
     *   resolución desde la creación.
     */
    private function isBreached(Ticket $ticket, Collection $rules): bool
    {
        $rule = $rules->get($ticket->priority);

        if (! $rule) {
            return false;
        }

        $createdAt = $ticket->created_at;

        $responseDeadline = $createdAt->copy()->addHours($rule->response_hours);

        if ($ticket->first_response_at === null && now()->greaterThan($responseDeadline)) {
            return true;
        }

        $resolutionDeadline = $createdAt->copy()->addHours($rule->resolution_hours);

        return now()->greaterThan($resolutionDeadline);
    }

    /**
     * Marca el incumplimiento y escala la prioridad del ticket.
     *
     * Devuelve 1 si hubo escalamiento de prioridad, 0 si ya estaba en la
     * máxima.
     */
    private function escalate(Ticket $ticket): int
    {
        $previous = $ticket->priority;
        $target = self::ESCALATION[$previous] ?? $previous;

        $ticket->forceFill([
            'sla_violated' => true,
            'priority' => $target,
        ])->saveQuietly();

        return $target !== $previous ? 1 : 0;
    }

    /**
     * Alerta al supervisor del departamento y al agente asignado del ticket.
     */
    private function notify(Ticket $ticket): void
    {
        $recipients = User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($ticket) {
                $query->where(fn ($q) => $q->whereHas('role', fn ($r) => $r->where('name', 'supervisor'))
                    ->where('department_id', $ticket->department_id));

                if ($ticket->assigned_to) {
                    $query->orWhere('id', $ticket->assigned_to);
                }
            })
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new SlaBreached($ticket));
    }
}

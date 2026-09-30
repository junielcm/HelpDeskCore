<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HandlesTicketAttachments;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de tickets.
 *
 * Cubre el ciclo de vida de una solicitud de soporte: listado (con filtros
 * por rol), creación por parte del cliente, detalles y actualización por el
 * staff. La visibilidad de cada ticket depende del rol que la consulta.
 */
class TicketController extends Controller
{
    use HandlesTicketAttachments;

    /**
     * Lista los tickets aplicando un filtro de visibilidad por rol.
     *
     * - client      → solo puede ver sus propios tickets.
     * - agent       → solo los tickets de su departamento.
     * - supervisor/admin → puede ver todos los tickets.
     *
     * Admite paginación opcional para no volcar todos los registros de golpe.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Ticket::with(['client', 'department', 'assignedAgent']);

        $query = $this->scopeByRole($query, $request->user());

        $tickets = $request->boolean('paginate')
            ? $query->paginate($request->integer('per_page', 15))
            : $query->get();

        return response()->json(['tickets' => $tickets]);
    }

    /**
     * Crea un nuevo ticket a nombre del cliente autenticado.
     *
     * Las prioridades posibles son low, medium, high y urgent; si no se
     * envía ninguna, el ticket nace con prioridad media por defecto.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string'],
            'description' => ['required', 'string'],
            'department_id' => ['required', 'exists:departments,id'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
            'attachment' => $this->attachmentRules(),
        ]);

        $ticket = Ticket::create([
            'title' => $data['title'],
            'description' => $data['description'],
            'department_id' => $data['department_id'],
            'priority' => $data['priority'] ?? 'medium',
            'client_id' => $request->user()->id,
        ]);

        if ($request->hasFile('attachment')) {
            TicketAttachment::create(
                $this->storeAttachment($request->file('attachment'), $ticket->id)
            );
        }

        $ticket->load(['client', 'department', 'assignedAgent', 'attachments']);

        return response()->json(['ticket' => $ticket], 201);
    }

    /**
     * Muestra los detalles completos de un ticket.
     *
     * Incluye comentarios y archivos adjuntos. Antes de responder valida que
     * el usuario tenga permiso para ver el ticket solicitado; si es cliente,
     * las notas internas del staff se filtran para no exponerlas.
     */
    public function show(Ticket $ticket): JsonResponse
    {
        $user = request()->user();

        $this->authorizeAccess($ticket, $user);

        $ticket->load(['client', 'department', 'assignedAgent', 'attachments']);

        $ticket->setRelation('comments', $ticket->comments()
            ->with(['user.role', 'attachments'])
            ->when($user->role->name === 'client', fn ($query) => $query->where('is_internal', false))
            ->get());

        return response()->json(['ticket' => $ticket]);
    }

    /**
     * Actualiza un ticket existente (status, prioridad o reasignación).
     *
     * - El staff (agent/supervisor/admin) puede cambiar el estado.
     * - Editar la prioridad es capacidad de la gestión operativa (supervisor/admin).
     * - Un agente puede tomar para sí mismo un ticket en cola de su departamento,
     *   pero solo resolver o cerrar tickets que tiene asignados.
     * - Reasignar a otro agente es reservado a supervisor/admin.
     * - Un cliente no puede modificar sus tickets, solo consultarlos y comentarlos.
     *
     * Cada cambio relevante queda registrado en la bitácora de auditoría.
     */
    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        $this->authorizeAccess($ticket, $user);

        if ($user->role->name === 'client') {
            return response()->json([
                'message' => 'No tienes permisos para actualizar tickets',
            ], 403);
        }

        $canManage = $this->isManager($user);

        if (! $canManage && $request->has('priority')) {
            return response()->json([
                'message' => 'Editar la prioridad está reservado a supervisor o administrador',
            ], 403);
        }

        if (! $canManage && $request->has('assigned_to')) {
            $target = $request->integer('assigned_to');

            $isSelfClaim = $target === $user->id
                && ($ticket->assigned_to === null || $ticket->assigned_to === $user->id);

            if (! $isSelfClaim) {
                return response()->json([
                    'message' => 'Solo un supervisor o administrador puede reasignar tickets',
                ], 403);
            }
        }

        $data = $request->validate([
            'status' => ['sometimes', 'in:open,in_progress,resolved,closed'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
            'assigned_to' => ['sometimes', 'nullable', 'exists:users,id'],
        ]);

        if (! $canManage
            && in_array($data['status'] ?? null, ['resolved', 'closed'], true)
            && $ticket->assigned_to !== $user->id) {
            return response()->json([
                'message' => 'Solo el agente asignado puede resolver o cerrar el ticket',
            ], 403);
        }

        $ticket->update($data);

        $ticket->load(['client', 'department', 'assignedAgent']);

        return response()->json(['ticket' => $ticket]);
    }

    /**
     * Lista los agentes activos del departamento del ticket para reasignación.
     *
     * Solo la gestión operativa (supervisor/admin) puede consultar la lista.
     */
    public function agents(Ticket $ticket): JsonResponse
    {
        $user = request()->user();

        $this->authorizeAccess($ticket, $user);

        abort_unless($this->isManager($user), 403, 'No tienes permisos para reasignar tickets');

        $agents = User::query()
            ->where('department_id', $ticket->department_id)
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('name', 'agent'))
            ->with(['role', 'department'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role_id', 'department_id']);

        return response()->json(['agents' => $agents]);
    }

    /**
     * Aplica el filtro de visibilidad según el rol del usuario.
     */
    private function scopeByRole($query, $user)
    {
        if (! $user->relationLoaded('role')) {
            $user->load('role');
        }

        return match ($user->role->name) {
            'client' => $query->where('client_id', $user->id),
            'agent' => $query->where('department_id', $user->department_id),
            default => $query,
        };
    }

    /**
     * Valida que el usuario tenga permiso para operar sobre un ticket.
     *
     * - client    → solo los tickets que él mismo creó.
     * - agent     → los tickets de su departamento o los que tiene asignados.
     * - supervisor/admin → acceso libre.
     */
    private function authorizeAccess(Ticket $ticket, $user): void
    {
        if (! $user->relationLoaded('role')) {
            $user->load('role');
        }

        $canAccess = match ($user->role->name) {
            'client' => $ticket->client_id === $user->id,
            'agent' => $ticket->department_id === $user->department_id || $ticket->assigned_to === $user->id,
            default => true,
        };

        abort_unless($canAccess, 403, 'No tienes permisos para acceder a este ticket');
    }

    /**
     * Determina si el usuario pertenece a la gestión operativa (supervisor/admin).
     */
    private function isManager($user): bool
    {
        if (! $user->relationLoaded('role')) {
            $user->load('role');
        }

        return in_array($user->role->name, ['supervisor', 'admin']);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HandlesTicketAttachments;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de comentarios de tickets.
 *
 * Gestiona la conversación dentro de cada ticket. Soporta notas internas
 * (visibles solo para el staff) que además quedan registradas en la bitácora
 * de auditoría del ticket.
 */
class CommentController extends Controller
{
    use HandlesTicketAttachments;

    /**
     * Lista los comentarios asociados a un ticket, con su autor y adjuntos.
     *
     * Un cliente solo ve los comentarios públicos (is_internal = false);
     * el staff ve también las notas internas.
     */
    public function index(Ticket $ticket): JsonResponse
    {
        $user = request()->user();

        $this->authorizeAccess($ticket, $user);

        $comments = $ticket->comments()
            ->with(['user.role', 'attachments'])
            ->when($user->role->name === 'client', fn ($query) => $query->where('is_internal', false))
            ->latest()
            ->get();

        return response()->json(['comments' => $comments]);
    }

    /**
     * Crea un comentario dentro de un ticket.
     *
     * Solo el staff puede marcar un comentario como nota interna. Los tickets
     * cerrados no admiten comentarios. Si la nota es interna, se registra en
     * la bitácora de auditoría para dejar rastro de la decisión.
     */
    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        $isInternal = $this->isStaff($user) && $request->boolean('is_internal');

        $this->authorizeAccess($ticket, $user);

        if ($ticket->status === 'closed') {
            return response()->json([
                'message' => 'No se permiten comentarios en tickets cerrados',
            ], 422);
        }

        $data = $request->validate([
            'body' => ['required', 'string'],
            'attachment' => $this->attachmentRules(),
        ]);

        $comment = Comment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'body' => $data['body'],
            'is_internal' => $isInternal,
        ]);

        // La primera respuesta pública del staff fija la marca de tiempo que
        // sirve de base para evaluar el SLA de atención (primera respuesta).
        if (! $isInternal && ! $ticket->first_response_at && $this->isStaff($user)) {
            $ticket->forceFill(['first_response_at' => now()])->saveQuietly();
        }

        if ($request->hasFile('attachment')) {
            TicketAttachment::create(
                $this->storeAttachment($request->file('attachment'), $ticket->id, $comment->id)
            );
        }

        $comment->load(['user.role', 'attachments']);

        if ($isInternal) {
            TicketAuditLog::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'action' => 'note_added',
                'new_value' => $data['body'],
            ]);
        }

        return response()->json(['comment' => $comment], 201);
    }

    /**
     * Determina si el usuario pertenece al staff (agent/supervisor/admin).
     */
    private function isStaff($user): bool
    {
        if (! $user->relationLoaded('role')) {
            $user->load('role');
        }

        return in_array($user->role->name, ['agent', 'supervisor', 'admin']);
    }

    /**
     * Valida que el usuario tenga permiso para acceder al ticket.
     */
    private function authorizeAccess(Ticket $ticket, $user): void
    {
        if (! $user->relationLoaded('role')) {
            $user->load('role');
        }

        $isClient = $user->role->name === 'client';

        abort_if($isClient && $ticket->client_id !== $user->id, 403);
    }
}

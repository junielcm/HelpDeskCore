<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketAuditLog;
use App\Models\TicketRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador del panel de métricas globales.
 *
 * Restringido a la gestión operativa (supervisor/admin). Expone los KPIs de
 * rendimiento, la distribución de carga de trabajo por departamento y estado,
 * y la bitácora forense de auditoría con filtros para trazabilidad.
 */
class DashboardController extends Controller
{
    private const OPEN_STATUSES = ['open', 'in_progress'];

    /**
     * KPIs globales consolidados del panel ejecutivo.
     *
     * Devuelve en una sola respuesta los indicadores de rendimiento, la
     * distribución por estado (dona), la carga de trabajo por departamento
     * (barras) y la tendencia de creación de tickets por día, para alimentar
     * todos los gráficos del dashboard en una única petición.
     */
    public function metrics(Request $request): JsonResponse
    {
        $this->authorizeManagement($request->user());

        $openQuery = Ticket::whereIn('status', self::OPEN_STATUSES);

        $criticalUnassigned = (clone $openQuery)
            ->whereIn('priority', ['high', 'urgent'])
            ->whereNull('assigned_to')
            ->count();

        $avgResponseTime = $this->averageResponseTime();
        $avgResolutionTime = $this->averageResolutionTime();

        $ratingsTotal = TicketRating::count();
        $positiveRatings = TicketRating::where('rating', '>=', 4)->count();

        return response()->json([
            'metrics' => [
                // KPIs generales
                'total_tickets' => Ticket::count(),
                'open_tickets' => $openQuery->count(),
                'in_progress_tickets' => Ticket::where('status', 'in_progress')->count(),
                'resolved_tickets' => Ticket::whereIn('status', ['resolved', 'closed'])->count(),
                'critical_unassigned' => $criticalUnassigned,
                'avg_response_time_seconds' => $avgResponseTime,
                'avg_resolution_time_seconds' => $avgResolutionTime,
                'satisfaction' => $ratingsTotal > 0
                    ? round(($positiveRatings / $ratingsTotal) * 100, 1)
                    : null,
                'ratings_count' => $ratingsTotal,

                // Distribución y tendencias para los gráficos
                'by_status' => $this->statusDistribution(),
                'by_department' => $this->departmentWorkload(),
                'trend' => $this->creationTrend(14),
            ],
        ]);
    }

    /**
     * Distribución de tickets por estado.
     *
     * @return array<string, int>
     */
    private function statusDistribution(): array
    {
        return Ticket::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->status => (int) $row->total])
            ->all();
    }

    /**
     * Carga de trabajo por departamento activo.
     *
     * @return array<int, array{department: string, count: int}>
     */
    private function departmentWorkload(): array
    {
        return Department::query()
            ->where('is_active', true)
            ->withCount(['tickets'])
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department) => [
                'department' => $department->name,
                'count' => $department->tickets_count,
            ])
            ->all();
    }

    /**
     * Volumen de tickets creados por día en los últimos N días.
     *
     * Devuelve una serie con todos los días (incluidos los de cero creaciones)
     * para alimentar el gráfico de tendencia sin huecos.
     *
     * @return array<int, array{date: string, count: int}>
     */
    private function creationTrend(int $days): array
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $counts = Ticket::query()
            ->where('created_at', '>=', $from)
            ->selectRaw("to_char(created_at, 'YYYY-MM-DD') as date, count(*) as total")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('total', 'date');

        $trend = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $trend[] = [
                'date' => $date,
                'count' => (int) ($counts[$date] ?? 0),
            ];
        }

        return $trend;
    }

    /**
     * Distribución de carga de trabajo para los gráficos del panel.
     *
     * Devuelve el número de tickets por departamento y por estado, y el total
     * de tickets considerados para contextualizar los porcentajes.
     */
    public function distribution(Request $request): JsonResponse
    {
        $this->authorizeManagement($request->user());

        $byDepartment = Department::query()
            ->where('is_active', true)
            ->withCount(['tickets'])
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department) => [
                'department' => $department->name,
                'count' => $department->tickets_count,
            ]);

        $byStatus = Ticket::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->status => (int) $row->total])
            ->all();

        return response()->json([
            'distribution' => [
                'total_tickets' => Ticket::count(),
                'by_department' => $byDepartment,
                'by_status' => $byStatus,
            ],
        ]);
    }

    /**
     * Bitácora de auditoría con filtros.
     *
     * La tabla de ticket_audit_logs es un historial de solo lectura; aquí el
     * administrador/supervisor puede filtrar por usuario, acción y rango de
     * fechas para reconstruir qué cambió y a qué hora.
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $this->authorizeManagement($request->user());

        $data = $request->validate([
            'user_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'action' => ['sometimes', 'nullable', 'string', 'max:255'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ]);

        $logs = TicketAuditLog::query()
            ->with(['user:id,name', 'ticket:id,title'])
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $data['action']))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $data['date_from']))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $data['date_to']))
            ->latest('created_at')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return response()->json(['audit_logs' => $logs]);
    }

    /**
     * Registra la calificación de satisfacción de un cliente sobre un ticket.
     *
     * Solo el cliente dueño del ticket puede calificar y únicamente cuando el
     * ticket ya está resuelto o cerrado. Un ticket se califica una sola vez.
     */
    public function storeRating(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        if (! $user->relationLoaded('role')) {
            $user->load('role');
        }

        abort_unless($user->role->name === 'client', 403, 'Solo el cliente puede calificar');

        if ($ticket->client_id !== $user->id) {
            abort(403, 'No puedes calificar un ticket que no es tuyo');
        }

        abort_unless(in_array($ticket->status, ['resolved', 'closed']), 422, 'El ticket debe estar resuelto o cerrado');

        abort_if(TicketRating::where('ticket_id', $ticket->id)->exists(), 409, 'Este ticket ya fue calificado');

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'feedback' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $rating = TicketRating::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'rating' => $data['rating'],
            'feedback' => $data['feedback'] ?? null,
        ]);

        return response()->json(['rating' => $rating], 201);
    }

    /**
     * Tiempo medio (en segundos) entre la creación del ticket y la primera
     * respuesta del staff (primer comentario de un agente/supervisor/admin).
     */
    private function averageResponseTime(): ?int
    {
        $firstResponses = Ticket::query()
            ->whereHas('comments', fn ($query) => $query
                ->whereHas('user.role', fn ($role) => $role->whereIn('name', ['agent', 'supervisor', 'admin'])))
            ->with(['comments' => fn ($query) => $query
                ->whereHas('user.role', fn ($role) => $role->whereIn('name', ['agent', 'supervisor', 'admin']))
                ->oldest()])
            ->get()
            ->map(fn (Ticket $ticket) => $ticket->created_at->diffInSeconds($ticket->comments->first()->created_at));

        if ($firstResponses->isEmpty()) {
            return null;
        }

        return (int) round($firstResponses->avg());
    }

    /**
     * Tiempo medio (en segundos) que tardan en resolverse los tickets.
     *
     * Se calcula a partir de la bitácora: el instante en que el ticket pasó a
     * estado resolved/closed menos su fecha de creación.
     */
    private function averageResolutionTime(): ?int
    {
        $resolutions = TicketAuditLog::query()
            ->where('action', 'status_changed')
            ->whereIn('new_value', ['resolved', 'closed'])
            ->get()
            ->map(fn (TicketAuditLog $log) => $log->ticket->created_at->diffInSeconds($log->created_at))
            ->filter(fn (int $seconds) => $seconds >= 0);

        if ($resolutions->isEmpty()) {
            return null;
        }

        return (int) round($resolutions->avg());
    }

    /**
     * Restringe el acceso a la gestión operativa (supervisor/admin).
     */
    private function authorizeManagement($user): void
    {
        if (! $user->relationLoaded('role')) {
            $user->load('role');
        }

        abort_unless(in_array($user->role->name, ['supervisor', 'admin']), 403);
    }
}

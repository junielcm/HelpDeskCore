<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SlaRule;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Configuración y estado del SLA (Acuerdo de Nivel de Servicio).
 *
 * Permite a la gestión operativa (supervisor/admin) consultar y ajustar los
 * umbrales de tiempo por prioridad, así como consultar el estado general de
 * cumplimiento de los tickets frente a esos acuerdos.
 */
class SlaController extends Controller
{
    /**
     * Lista las reglas de SLA configuradas por prioridad.
     */
    public function rules(): JsonResponse
    {
        $this->authorizeManagement(request()->user());

        $rules = SlaRule::orderByRaw(
            "array_position(ARRAY['urgent','high','medium','low'], priority)"
        )->get();

        return response()->json(['rules' => $rules]);
    }

    /**
     * Actualiza los umbrales de una regla de SLA (respuesta/resolución).
     */
    public function updateRule(Request $request, SlaRule $rule): JsonResponse
    {
        $this->authorizeManagement($request->user());

        $data = $request->validate([
            'response_hours' => ['sometimes', 'integer', 'min:1', 'max:8760'],
            'resolution_hours' => ['sometimes', 'integer', 'min:1', 'max:8760'],
        ]);

        $rule->update($data);

        return response()->json(['rule' => $rule->fresh()]);
    }

    /**
     * Estado general de cumplimiento de SLA para el panel ejecutivo.
     *
     * Resume cuántos tickets abiertos/en proceso incumplieron su SLA frente a
     * los que siguen dentro del acuerdo, más el desglose por prioridad.
     */
    public function status(): JsonResponse
    {
        $this->authorizeManagement(request()->user());

        $total = Ticket::whereIn('status', ['open', 'in_progress'])->count();
        $breached = Ticket::whereIn('status', ['open', 'in_progress'])
            ->where('sla_violated', true)
            ->count();

        $compliance = $total > 0 ? round((($total - $breached) / $total) * 100, 1) : null;

        $byPriority = Ticket::whereIn('status', ['open', 'in_progress'])
            ->selectRaw('priority, count(*) filter (where sla_violated) as breached, count(*) as total')
            ->groupBy('priority')
            ->orderBy('priority')
            ->get()
            ->map(fn ($row) => [
                'priority' => $row->priority,
                'total' => (int) $row->total,
                'breached' => (int) $row->breached,
                'compliant' => (int) $row->total - (int) $row->breached,
            ])
            ->values();

        return response()->json([
            'sla' => [
                'total_tickets' => $total,
                'breached' => $breached,
                'compliant' => $total - $breached,
                'compliance_percent' => $compliance,
                'by_priority' => $byPriority,
            ],
        ]);
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

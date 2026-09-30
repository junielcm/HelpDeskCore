<?php

namespace App\Http\Controllers\Api;

use App\Exports\TicketsExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Controlador de reportes.
 *
 * Exporta la información de los tickets a hojas de cálculo. Todo el bloque
 * está restringido a los roles supervisor y admin.
 */
class ReportController extends Controller
{
    /**
     * Descarga un reporte .xlsx de tickets según los filtros opcionales
     * (estatus, departamento, prioridad y rango de fechas). Solo supervisor
     * o admin.
     */
    public function ticketsExcel(Request $request): BinaryFileResponse
    {
        $this->authorizeReport($request->user());

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:open,in_progress,resolved,closed'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $fileName = 'reporte-tickets-'.now()->format('Y-m-d-Hi').'.xlsx';

        return Excel::download(new TicketsExport($filters), $fileName);
    }

    /**
     * Restringe el acceso al reporte (supervisor y admin).
     */
    private function authorizeReport($user): void
    {
        if (! $user->relationLoaded('role')) {
            $user->load('role');
        }

        abort_unless(in_array($user->role->name, ['supervisor', 'admin']), 403);
    }
}

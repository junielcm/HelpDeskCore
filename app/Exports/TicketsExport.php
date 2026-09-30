<?php

namespace App\Exports;

use App\Models\Ticket;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exportador de tickets a una hoja de cálculo (Excel/xlsx).
 *
 * Aplica los filtros opcionales recibidos desde el reporte (estatus,
 * departamento, prioridad y rango de fechas) y mapea cada ticket a una fila
 * con los datos de sus relaciones (solicitante, departamento y agente).
 */
class TicketsExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  array{status?: string, department_id?: int, priority?: string, date_from?: string, date_to?: string}  $filters
     */
    public function __construct(
        private array $filters = [],
    ) {}

    /**
     * Consulta los tickets aplicando los filtros del reporte.
     *
     * @return Enumerable<int, Ticket>
     */
    public function collection(): Enumerable
    {
        return Ticket::with(['client', 'department', 'assignedAgent'])
            ->when(! empty($this->filters['status']), fn ($query) => $query->where('status', $this->filters['status']))
            ->when(! empty($this->filters['department_id']), fn ($query) => $query->where('department_id', $this->filters['department_id']))
            ->when(! empty($this->filters['priority']), fn ($query) => $query->where('priority', $this->filters['priority']))
            ->when(! empty($this->filters['date_from']), fn ($query) => $query->whereDate('created_at', '>=', $this->filters['date_from']))
            ->when(! empty($this->filters['date_to']), fn ($query) => $query->whereDate('created_at', '<=', $this->filters['date_to']))
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Encabezados de las columnas del reporte.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'ID',
            'Asunto',
            'Solicitante',
            'Departamento',
            'Agente Asignado',
            'Prioridad',
            'Estado',
            'Fecha de Creación',
        ];
    }

    /**
     * Mapea un ticket a una fila de la hoja de cálculo.
     *
     * @return array<int, string|int|null>
     */
    public function map(mixed $row): array
    {
        return [
            $row->id,
            $row->title,
            $row->client?->name,
            $row->department?->name,
            $row->assignedAgent?->name,
            $this->priorityLabel($row->priority),
            $this->statusLabel($row->status),
            $row->created_at?->format('d/m/Y H:i'),
        ];
    }

    /**
     * Etiqueta legible de la prioridad.
     */
    private function priorityLabel(?string $priority): ?string
    {
        return [
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            'urgent' => 'Urgente',
        ][$priority] ?? $priority;
    }

    /**
     * Etiqueta legible del estado.
     */
    private function statusLabel(?string $status): ?string
    {
        return [
            'open' => 'Abierto',
            'in_progress' => 'En Proceso',
            'resolved' => 'Resuelto',
            'closed' => 'Cerrado',
        ][$status] ?? $status;
    }
}

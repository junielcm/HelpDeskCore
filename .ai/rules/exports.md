---
paths:
  - 'app/Exports/**'
---

# Exports

## Exportación de tickets con Laravel-Excel v4
El reporte de tickets usa maatwebsite/excel 4.0 (requiere extensión PHP 'gd', habilitada en C:\php\php.ini). TicketsExport implementa FromCollection+WithHeadings+WithMapping y recibe los filtros (status, priority, department_id, date_from, date_to) por constructor. En v4 collection() devuelve Illuminate\Support\Enumerable y la descarga se hace con Excel::download($export, $filename) desde la fachada Maatwebsite\Excel\Facades\Excel.

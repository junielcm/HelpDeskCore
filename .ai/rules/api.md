---
paths:
  - app/Http/Controllers/Api/DashboardController.php
  - app/Http/Controllers/Api/AdminController.php
  - app/Http/Controllers/Api/DepartmentManagementController.php
  - app/Http/Controllers/Api/ReportController.php
---

# Api

## Satisfacción se calcula desde tabla ticket_ratings
El KPI de satisfacción (%) se obtiene de la tabla ticket_ratings (campo rating 1-5; positivas >= 4). No hay campo de rating en tickets/comments. Los tickets solo pueden calificarse una vez (409 en duplicado) por el cliente dueño y solo si están resolved/closed.

## Endpoints de gestión de usuarios
Gestión de usuarios bajo /api/users (GET indexUsers, POST storeUser, PUT updateUser) + GET /api/roles. Restringido a supervisor/admin; el supervisor SOLO puede crear/editar roles client/agent y no puede gestionar a otros supervisores/admins. department_id es opcional (clientes sin departamento). updateUser solo aplica campos presentes y maneja false correctamente (usar array_key_exists, no array_filter).

## Gestión de departamentos (solo admin)
La gestión de departamentos vive en DepartmentManagementController, restringida EXCLUSIVAMENTE al rol admin: GET /api/admin/departments (lista completa activos+inactivos con users_count), POST /api/departments y PUT /api/departments/{department}. El catálogo público GET /api/departments (DepartmentController, solo activos, cualquier autenticado) se mantiene para formularios y NO debe fusionarse con la gestión.

## Endpoint de reporte de tickets
ReportController expone GET /api/reports/tickets/excel (protegida por Sanctum, roles supervisor/admin). La fachada Excel devuelve BinaryFileResponse; en tests se valida el header content-disposition como 'attachment; filename=reporte-tickets-*' (sin name=). El frontend descarga con responseType 'blob' desde stores/reports.js.

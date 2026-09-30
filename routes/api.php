<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DepartmentManagementController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SlaController;
use App\Http\Controllers\Api\TicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas del API
|--------------------------------------------------------------------------
|
| Todas las rutas viven bajo el prefijo /api y responden con JSON. El login
| es la única ruta pública; el resto exige un token Sanctum emitido al
| iniciar sesión (middleware auth:sanctum).
|
*/

// --- Autenticación pública -------------------------------------------------
Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);

// --- Rutas protegidas (requieren token de acceso) --------------------------
Route::middleware('auth:sanctum')->group(function () {
    // Sesión
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('profile', [AuthController::class, 'profile']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::put('profile/password', [ProfileController::class, 'updatePassword']);

    // Notificaciones del usuario autenticado
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::put('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::put('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);

    // Tickets
    Route::get('tickets', [TicketController::class, 'index']);
    Route::post('tickets', [TicketController::class, 'store']);
    Route::get('tickets/{ticket}', [TicketController::class, 'show']);
    Route::put('tickets/{ticket}', [TicketController::class, 'update']);
    Route::get('tickets/{ticket}/agents', [TicketController::class, 'agents']);

    // Calificación de satisfacción (solo el cliente propietario)
    Route::post('tickets/{ticket}/ratings', [DashboardController::class, 'storeRating']);

    // Catálogo de departamentos (para formularios del frontend)
    Route::get('departments', [DepartmentController::class, 'index']);

    // Comentarios de un ticket
    Route::get('tickets/{ticket}/comments', [CommentController::class, 'index']);
    Route::post('tickets/{ticket}/comments', [CommentController::class, 'store']);

    // Panel de métricas globales (solo supervisor/admin, validado en el controlador)
    Route::get('dashboard/metrics', [DashboardController::class, 'metrics']);
    Route::get('dashboard/distribution', [DashboardController::class, 'distribution']);
    Route::get('dashboard/audit-logs', [DashboardController::class, 'auditLogs']);

    // Administración de usuarios y catálogo de roles (solo supervisor/admin,
    // validado dentro del controlador)
    Route::get('users', [AdminController::class, 'indexUsers']);
    Route::post('users', [AdminController::class, 'storeUser']);
    Route::put('users/{user}', [AdminController::class, 'updateUser']);
    Route::get('roles', [AdminController::class, 'roles']);

    // Administración de departamentos (solo admin). La lista completa vive en
    // /admin/departments, mientras que el alta/edición ocupa POST/PUT en
    // /api/departments sin chocar con el catálogo público GET que usan los
    // formularios.
    Route::get('admin/departments', [DepartmentManagementController::class, 'index']);
    Route::post('departments', [DepartmentManagementController::class, 'store']);
    Route::put('departments/{department}', [DepartmentManagementController::class, 'update']);

    // Reportes (solo supervisor/admin, validado en el controlador)
    Route::get('reports/tickets/excel', [ReportController::class, 'ticketsExcel']);

    // SLA: configuración de umbrales y estado de cumplimiento
    // (solo supervisor/admin, validado en el controlador)
    Route::get('sla/rules', [SlaController::class, 'rules']);
    Route::put('sla/rules/{rule}', [SlaController::class, 'updateRule']);
    Route::get('sla/status', [SlaController::class, 'status']);
});

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Controlador de notificaciones del usuario autenticado.
 *
 * Expone el listado de notificaciones en base de datos, el marcado de una
 * como leída y el marcado masivo de todas como leídas. Siempre opera sobre
 * las notificaciones del usuario de la sesión, sin exponer las de otros.
 */
class NotificationController extends Controller
{
    /**
     * Lista las notificaciones del usuario logueado, más recientes primero,
     * junto con el total de no leídas para el badge de la campana.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $unreadCount = $user->unreadNotifications()->count();

        $notifications = $user->notifications()
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Marca una notificación concreta del usuario como leída.
     */
    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $record = $this->ownedNotification($request->user(), $notification);

        if ($record) {
            $record->markAsRead();
        }

        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Marca todas las notificaciones del usuario como leídas.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'unread_count' => 0,
        ]);
    }

    /**
     * Busca una notificación de la base de datos acotada al usuario de la
     * sesión. Si el identificador no pertenece al usuario, devuelve null.
     */
    private function ownedNotification(User $user, string $id): ?DatabaseNotification
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->whereKey($id)
            ->first();
    }
}

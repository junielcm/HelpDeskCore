import { ref } from 'vue';
import { defineStore } from 'pinia';

import http from '../services/axios';

/*
 * Store global de las notificaciones en base de datos del usuario.
 *
 * Centraliza el listado, el contador de no leídas (para el badge de la
 * campana del layout) y las acciones de marcado de notificaciones leídas.
 */
export const useNotificationsStore = defineStore('notifications', () => {
    const notifications = ref([]);
    const unreadCount = ref(0);
    const loading = ref(false);
    const error = ref(null);

    async function fetchNotifications() {
        loading.value = true;
        error.value = null;

        try {
            const { data } = await http.get('/notifications', { params: { per_page: 20 } });
            notifications.value = data.notifications?.data ?? [];
            unreadCount.value = Number(data.unread_count ?? 0);
        } catch (err) {
            error.value = err.response?.data?.message ?? 'No se pudieron cargar las notificaciones.';
        } finally {
            loading.value = false;
        }
    }

    async function markAsRead(notificationId) {
        try {
            const { data } = await http.put(`/notifications/${notificationId}/read`);
            unreadCount.value = Number(data.unread_count ?? 0);

            const target = notifications.value.find((n) => n.id === notificationId);
            if (target) {
                target.read_at = new Date().toISOString();
            }
        } catch {
            // se ignora: el marcado de lectura es una mejora, no un bloqueo
        }
    }

    async function markAllAsRead() {
        try {
            await http.put('/notifications/read-all');
            unreadCount.value = 0;
            notifications.value = notifications.value.map((n) => ({
                ...n,
                read_at: n.read_at ?? new Date().toISOString(),
            }));
        } catch {
            // se ignora
        }
    }

    return {
        notifications,
        unreadCount,
        loading,
        error,
        fetchNotifications,
        markAsRead,
        markAllAsRead,
    };
});

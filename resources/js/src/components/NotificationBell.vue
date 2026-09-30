<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';

import { useNotificationsStore } from '../stores/notifications';

const router = useRouter();
const store = useNotificationsStore();
const { notifications, unreadCount, loading } = storeToRefs(store);

const open = ref(false);
const bellRef = ref(null);

const typeLabels = {
    TicketCreated: 'Nuevo ticket',
    TicketAssigned: 'Ticket asignado',
    TicketStatusChanged: 'Estado actualizado',
};

const statusLabels = {
    open: 'Abierto',
    in_progress: 'En Proceso',
    resolved: 'Resuelto',
    closed: 'Cerrado',
};

const badgeLabel = computed(() => {
    return unreadCount.value > 99 ? '99+' : String(unreadCount.value);
});

function baseMessage(type, data) {
    const map = {
        TicketCreated: 'Se creó un ticket en tu departamento',
        TicketAssigned: 'Te asignaron un ticket',
        TicketStatusChanged: 'Tu ticket cambió de estado',
    };

    return map[type] ?? 'Nueva notificación';
}

function detailMessage(type, data) {
    if (type === 'TicketStatusChanged') {
        return `Estado: ${statusLabels[data.status] ?? data.status}`;
    }
    if (data.department_name) {
        return data.department_name;
    }
    if (data.priority) {
        return `Prioridad: ${data.priority}`;
    }
    return '';
}

function parseData(notification) {
    if (typeof notification.data === 'string') {
        try {
            return JSON.parse(notification.data);
        } catch {
            return {};
        }
    }
    return notification.data ?? {};
}

function notificationViewModel(notification) {
    const data = parseData(notification);
    const type = notification.type?.split('\\').pop() ?? '';

    return {
        id: notification.id,
        message: baseMessage(type, data),
        detail: detailMessage(type, data),
        ticketId: data.ticket_id,
        readAt: notification.read_at,
        createdAt: formatDate(notification.created_at),
        type,
    };
}

function formatDate(value) {
    if (!value) return '';
    const date = new Date(value);
    const now = Date.now();
    const diffMs = now - date.getTime();
    const minutes = Math.floor(diffMs / 60000);

    if (minutes < 1) return 'ahora mismo';
    if (minutes < 60) return `hace ${minutes} min`;

    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `hace ${hours} h`;

    return date.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
}

function toggle() {
    open.value = !open.value;

    if (open.value) {
        store.fetchNotifications();
    }
}

async function openNotification(notification) {
    const view = notificationViewModel(notification);

    if (!view.readAt) {
        await store.markAsRead(notification.id);
    }

    open.value = false;

    if (view.ticketId) {
        router.push({ name: 'ticket-detail', params: { id: view.ticketId } });
    }
}

async function markAll() {
    await store.markAllAsRead();
}

function onClickOutside(event) {
    if (bellRef.value && !bellRef.value.contains(event.target)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('click', onClickOutside));
onUnmounted(() => document.removeEventListener('click', onClickOutside));
</script>

<template>
    <div ref="bellRef" class="relative">
        <button
            type="button"
            @click.stop="toggle"
            class="relative flex h-10 w-10 items-center justify-center rounded-xl bg-white/50 text-slate-500 backdrop-blur-xl transition hover:bg-white/80 hover:text-slate-700 border border-white/70"
            aria-label="Notificaciones"
        >
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M10 2a6 6 0 0 0-6 6v3.586l-.707.707A1 1 0 0 0 4 14h12a1 1 0 0 0 .707-1.707L16 11.586V8a6 6 0 0 0-6-6ZM10 18a3 3 0 0 1-3-3h6a3 3 0 0 1-3 3Z" />
            </svg>

            <span
                v-if="unreadCount > 0"
                class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white ring-2 ring-white"
            >
                {{ badgeLabel }}
            </span>
        </button>

        <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-1"
        >
            <div
                v-if="open"
                class="absolute right-0 top-12 z-50 w-80 overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200/70"
            >
                <header class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <div>
                        <p class="text-sm font-bold text-slate-800">Notificaciones</p>
                        <p class="text-xs text-slate-400">
                            {{ unreadCount }} sin leer
                        </p>
                    </div>
                    <button
                        v-if="unreadCount > 0"
                        type="button"
                        @click="markAll"
                        class="text-xs font-medium text-teal-700 transition hover:text-teal-600"
                    >
                        Marcar todas
                    </button>
                </header>

                <div class="max-h-80 overflow-y-auto">
                    <div v-if="loading" class="p-8 text-center">
                        <p class="text-sm text-slate-400">Cargando…</p>
                    </div>

                    <div v-else-if="notifications.length === 0" class="p-8 text-center">
                        <p class="text-sm font-medium text-slate-600">Sin notificaciones</p>
                        <p class="mt-1 text-xs text-slate-400">Estarás al tanto de lo nuevo aquí.</p>
                    </div>

                    <ul v-else class="divide-y divide-slate-100">
                        <li
                            v-for="notification in notifications"
                            :key="notification.id"
                            @click="openNotification(notification)"
                            class="cursor-pointer px-4 py-3 transition hover:bg-slate-50"
                        >
                            <div :class="notification.read_at ? 'opacity-70' : ''" class="flex items-start gap-3">
                                <span
                                    :class="notification.read_at
                                        ? 'bg-slate-100 text-slate-400'
                                        : 'bg-teal-50 text-teal-600'"
                                    class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M4.5 2A1.5 1.5 0 0 0 3 3.5v13A1.5 1.5 0 0 0 4.5 18h11a1.5 1.5 0 0 0 1.5-1.5V7.621a1.5 1.5 0 0 0-.44-1.06l-4.12-4.122A1.5 1.5 0 0 0 11.378 2H4.5Zm2.25 8.5a.75.75 0 0 0 0 1.5h6.5a.75.75 0 0 0 0-1.5h-6.5Zm0 3a.75.75 0 0 0 0 1.5h6.5a.75.75 0 0 0 0-1.5h-6.5Z" clip-rule="evenodd" />
                                    </svg>
                                </span>

                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold text-slate-700">
                                        {{ notificationViewModel(notification).message }}
                                    </p>
                                    <p v-if="notificationViewModel(notification).detail" class="mt-0.5 truncate text-xs text-slate-400">
                                        {{ notificationViewModel(notification).detail }}
                                    </p>
                                    <p class="mt-1 text-[11px] text-slate-300">
                                        {{ notificationViewModel(notification).createdAt }}
                                    </p>
                                </div>

                                <span v-if="!notification.read_at" class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-teal-500"></span>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </transition>
    </div>
</template>

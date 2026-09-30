<script setup>
import { onMounted, ref } from 'vue';

import http from '../services/axios';

const emit = defineEmits(['close']);

const actionLabels = {
    created: 'Creación',
    status_changed: 'Cambio de estado',
    reassigned: 'Reasignación',
    ticket_edited: 'Edición',
};

const loading = ref(false);
const error = ref(null);

const logs = ref([]);
const pagination = ref({ total: 0, per_page: 25, current_page: 1, last_page: 1 });

const filters = ref({
    user_id: '',
    action: '',
    date_from: '',
    date_to: '',
});

const userOptions = ref([]);

function resetFilters() {
    filters.value = { user_id: '', action: '', date_from: '', date_to: '' };
    loadLogs(1);
}

function applyFilters() {
    loadLogs(1);
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatValue(value, action) {
    if (value == null || value === '') return '—';

    if (action === 'status_changed') {
        const map = {
            open: 'Abierto',
            in_progress: 'En Proceso',
            resolved: 'Resuelto',
            closed: 'Cerrado',
        };
        return map[value] ?? value;
    }

    if (action === 'reassigned') {
        if (value === 'null') return 'En cola';
        return `#${value}`;
    }

    return value;
}

function presentValue(value) {
    if (value == null || value === '') return '—';
    try {
        const parsed = JSON.parse(value);
        if (typeof parsed === 'object' && parsed !== null) {
            return Object.entries(parsed)
                .map(([key, val]) => `${key}: ${val}`)
                .join(' · ');
        }
    } catch {
        // no es JSON, se muestra tal cual
    }
    return value;
}

async function loadLogs(page = 1) {
    loading.value = true;
    error.value = null;

    const params = { page };

    for (const [key, val] of Object.entries(filters.value)) {
        if (val !== '' && val != null) {
            params[key] = val;
        }
    }

    try {
        const { data } = await http.get('/dashboard/audit-logs', { params });
        logs.value = data.audit_logs?.data ?? [];
        pagination.value = {
            total: data.audit_logs?.total ?? 0,
            per_page: data.audit_logs?.per_page ?? 25,
            current_page: data.audit_logs?.current_page ?? 1,
            last_page: data.audit_logs?.last_page ?? 1,
        };
    } catch (err) {
        error.value = err.response?.data?.message ?? 'No se pudo cargar la bitácora.';
    } finally {
        loading.value = false;
    }
}

function changePage(delta) {
    const target = pagination.value.current_page + delta;
    if (target < 1 || target > pagination.value.last_page) return;
    loadLogs(target);
}

onMounted(async () => {
    loadLogs(1);

    try {
        const { data } = await http.get('/users');
        userOptions.value = data.users ?? [];
    } catch {
        userOptions.value = [];
    }
});
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="emit('close')"></div>

        <div class="relative flex max-h-[88vh] w-full max-w-4xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl">
            <header class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-teal-700">Trazabilidad</p>
                    <h2 class="mt-0.5 text-xl font-bold text-slate-900">Bitácora de auditoría</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Registro inmutable de quién modificó qué y a qué hora.
                    </p>
                </div>

                <button
                    type="button"
                    @click="emit('close')"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                >
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                    </svg>
                </button>
            </header>

            <div class="px-6 py-4">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-slate-500">Usuario</span>
                        <select
                            v-model="filters.user_id"
                            class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm text-slate-700 ring-1 ring-slate-200 focus:ring-2 focus:ring-teal-600"
                        >
                            <option value="">Todos</option>
                            <option v-for="user in userOptions" :key="user.id" :value="user.id">
                                {{ user.name }}
                            </option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-slate-500">Acción</span>
                        <select
                            v-model="filters.action"
                            class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm text-slate-700 ring-1 ring-slate-200 focus:ring-2 focus:ring-teal-600"
                        >
                            <option value="">Todas</option>
                            <option v-for="(label, key) in actionLabels" :key="key" :value="key">
                                {{ label }}
                            </option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-slate-500">Desde</span>
                        <input
                            v-model="filters.date_from"
                            type="date"
                            class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm text-slate-700 ring-1 ring-slate-200 focus:ring-2 focus:ring-teal-600"
                        />
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-slate-500">Hasta</span>
                        <input
                            v-model="filters.date_to"
                            type="date"
                            class="w-full rounded-xl border-0 bg-slate-50 px-3 py-2 text-sm text-slate-700 ring-1 ring-slate-200 focus:ring-2 focus:ring-teal-600"
                        />
                    </label>
                </div>

                <div class="mt-3 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="resetFilters"
                        class="rounded-xl px-4 py-2 text-sm font-medium text-slate-500 transition hover:bg-slate-100"
                    >
                        Limpiar
                    </button>
                    <button
                        type="button"
                        @click="applyFilters"
                        class="rounded-xl bg-teal-800 px-5 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-teal-700"
                    >
                        Aplicar filtros
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-6 pb-4">
                <div v-if="loading" class="rounded-2xl bg-slate-50 p-12 text-center">
                    <p class="text-sm text-slate-500">Cargando bitácora…</p>
                </div>

                <div v-else-if="error" class="rounded-2xl bg-red-50 p-12 text-center">
                    <p class="text-sm font-medium text-red-600">{{ error }}</p>
                </div>

                <div v-else-if="logs.length === 0" class="rounded-2xl bg-slate-50 p-12 text-center">
                    <p class="text-sm font-medium text-slate-600">Sin registros</p>
                    <p class="mt-1 text-sm text-slate-500">No hay eventos que coincidan con los filtros.</p>
                </div>

                <div v-else class="overflow-hidden rounded-2xl ring-1 ring-slate-200">
                    <div class="grid grid-cols-12 gap-3 border-b border-slate-100 bg-slate-50 px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                        <span class="col-span-3">Fecha</span>
                        <span class="col-span-2">Usuario</span>
                        <span class="col-span-2">Acción</span>
                        <span class="col-span-5">Detalle</span>
                    </div>

                    <ul class="divide-y divide-slate-100">
                        <li v-for="log in logs" :key="log.id" class="grid grid-cols-12 gap-3 px-4 py-3 text-sm">
                            <span class="col-span-3 text-slate-500">{{ formatDate(log.created_at) }}</span>
                            <span class="col-span-2 truncate font-medium text-slate-700">{{ log.user?.name ?? '—' }}</span>
                            <span class="col-span-2">
                                <span class="inline-flex rounded-full bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-700 ring-1 ring-inset ring-teal-600/20">
                                    {{ actionLabels[log.action] ?? log.action }}
                                </span>
                            </span>
                            <span class="col-span-5 text-slate-500">
                                <template v-if="log.action === 'status_changed' || log.action === 'reassigned'">
                                    <span class="text-slate-400">{{ formatValue(log.old_value, log.action) }}</span>
                                    <span class="mx-1 text-slate-300">→</span>
                                    <span class="text-slate-700">{{ formatValue(log.new_value, log.action) }}</span>
                                </template>
                                <template v-else>
                                    <span class="text-slate-500">{{ presentValue(log.new_value) }}</span>
                                </template>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            <footer
                v-if="pagination.last_page > 1"
                class="flex items-center justify-between border-t border-slate-100 px-6 py-3"
            >
                <p class="text-xs text-slate-500">
                    {{ pagination.total }} registro{{ pagination.total === 1 ? '' : 's' }}
                </p>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        :disabled="pagination.current_page <= 1"
                        @click="changePage(-1)"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 disabled:opacity-40"
                    >
                        Anterior
                    </button>
                    <span class="text-sm text-slate-500">
                        {{ pagination.current_page }} / {{ pagination.last_page }}
                    </span>
                    <button
                        type="button"
                        :disabled="pagination.current_page >= pagination.last_page"
                        @click="changePage(1)"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 disabled:opacity-40"
                    >
                        Siguiente
                    </button>
                </div>
            </footer>
        </div>
    </div>
</template>

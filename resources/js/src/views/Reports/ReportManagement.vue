<script setup>
import { onMounted, reactive, ref } from 'vue';

import http from '../../services/axios';
import { useReportsStore } from '../../stores/reports';

const store = useReportsStore();

const departments = ref([]);
const downloadError = ref(null);

const statusOptions = [
    { value: 'open', label: 'Abierto' },
    { value: 'in_progress', label: 'En Proceso' },
    { value: 'resolved', label: 'Resuelto' },
    { value: 'closed', label: 'Cerrado' },
];

const priorityOptions = [
    { value: 'low', label: 'Baja' },
    { value: 'medium', label: 'Media' },
    { value: 'high', label: 'Alta' },
    { value: 'urgent', label: 'Urgente' },
];

const filters = reactive({
    status: '',
    priority: '',
    department_id: '',
    date_from: '',
    date_to: '',
});

function hasActiveFilters() {
    return Object.values(filters).some((value) => value !== '');
}

function clearFilters() {
    Object.assign(filters, {
        status: '',
        priority: '',
        department_id: '',
        date_from: '',
        date_to: '',
    });
}

async function handleDownload() {
    downloadError.value = null;

    const params = Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== ''),
    );

    try {
        await store.downloadTicketsExcel(params);
    } catch (err) {
        downloadError.value = store.error ?? 'No se pudo descargar el reporte.';
    }
}

onMounted(async () => {
    try {
        const { data } = await http.get('/departments');
        departments.value = data.departments ?? [];
    } catch {
        departments.value = [];
    }
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Encabezado -->
        <div>
            <p class="text-[11px] font-bold text-teal-700 tracking-wider uppercase">Reportes</p>
            <h2 class="text-xl font-black tracking-tight text-slate-900 mt-0.5">Exportación de Tickets</h2>
            <p class="mt-1 text-sm text-slate-500">Filtra los tickets y descarga un reporte en Excel (.xlsx).</p>
        </div>

        <!-- Panel de filtros -->
        <div class="rounded-2xl border border-slate-200/70 bg-white p-6 shadow-sm">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <label class="flex flex-col gap-1.5 text-sm font-medium text-slate-700">
                    Estado
                    <select
                        v-model="filters.status"
                        class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none transition focus:border-teal-800 focus:ring-2 focus:ring-teal-800/20"
                    >
                        <option value="">Todos</option>
                        <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </label>

                <label class="flex flex-col gap-1.5 text-sm font-medium text-slate-700">
                    Prioridad
                    <select
                        v-model="filters.priority"
                        class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none transition focus:border-teal-800 focus:ring-2 focus:ring-teal-800/20"
                    >
                        <option value="">Todas</option>
                        <option v-for="option in priorityOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </label>

                <label class="flex flex-col gap-1.5 text-sm font-medium text-slate-700">
                    Departamento
                    <select
                        v-model="filters.department_id"
                        class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none transition focus:border-teal-800 focus:ring-2 focus:ring-teal-800/20"
                    >
                        <option value="">Todos</option>
                        <option v-for="department in departments" :key="department.id" :value="department.id">
                            {{ department.name }}
                        </option>
                    </select>
                </label>

                <label class="flex flex-col gap-1.5 text-sm font-medium text-slate-700">
                    Desde
                    <input
                        v-model="filters.date_from"
                        type="date"
                        class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none transition focus:border-teal-800 focus:ring-2 focus:ring-teal-800/20"
                    />
                </label>

                <label class="flex flex-col gap-1.5 text-sm font-medium text-slate-700">
                    Hasta
                    <input
                        v-model="filters.date_to"
                        type="date"
                        class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none transition focus:border-teal-800 focus:ring-2 focus:ring-teal-800/20"
                    />
                </label>
            </div>

            <div class="mt-5 flex items-center justify-between gap-4 border-t border-slate-100 pt-5">
                <button
                    v-if="hasActiveFilters()"
                    type="button"
                    @click="clearFilters"
                    class="text-sm font-medium text-slate-500 transition hover:text-slate-700"
                >
                    Limpiar filtros
                </button>
                <span v-else></span>

                <button
                    type="button"
                    @click="handleDownload"
                    :disabled="store.loading"
                    class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-teal-600 to-cyan-500 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-teal-600/25 transition hover:from-teal-500 hover:to-cyan-400 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <svg v-if="store.loading" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path>
                    </svg>
                    <svg v-else class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z" />
                        <path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z" />
                    </svg>
                    {{ store.loading ? 'Generando…' : 'Descargar Excel' }}
                </button>
            </div>

            <p v-if="downloadError" class="mt-3 text-sm text-red-600">{{ downloadError }}</p>
        </div>

        <!-- Nota informativa -->
        <div class="rounded-2xl border border-teal-800/10 bg-teal-50/60 p-4 text-sm text-slate-600">
            El reporte incluye los campos: ID, Asunto, Solicitante, Departamento, Agente Asignado, Prioridad, Estado y Fecha de Creación.
            Deja todos los filtros vacíos para exportar el historial completo.
        </div>
    </div>
</template>

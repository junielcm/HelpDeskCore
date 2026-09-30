<script setup>
import { computed, onMounted, ref } from 'vue';

import AuditLogModal from '../../components/AuditLogModal.vue';
import http from '../../services/axios';
import { useSlaStore } from '../../stores/sla';
import { priorityOptions, statusOptions } from '../../utils/ticket-options';

const sla = useSlaStore();

const loading = ref(true);
const error = ref(null);

const metrics = ref(null);
const distribution = ref(null);

const showAuditLog = ref(false);
const savingRule = ref(null);

const slaPriorityLabel = computed(() => (priority) => priorityOptions[priority]?.label ?? priority);

const formatSeconds = computed(() => (seconds) => {
    if (seconds == null) return '—';

    const hours = seconds / 3600;

    if (hours >= 48) {
        return `${(hours / 24).toFixed(1).replace('.0', '')} d`;
    }

    if (hours >= 1) {
        return `${hours.toFixed(1).replace('.0', '')} h`;
    }

    return `${Math.round(seconds / 60)} min`;
});

const statusChart = computed(() => {
    const order = ['open', 'in_progress', 'resolved', 'closed'];
    const counts = distribution.value?.by_status ?? {};

    const rows = order
        .filter((key) => (counts[key] ?? 0) > 0)
        .map((key) => ({
            key,
            label: statusOptions[key]?.label ?? key,
            color: statusOptions[key]?.badge ?? '',
            count: counts[key] ?? 0,
        }));

    const total = rows.reduce((sum, row) => sum + row.count, 0);

    return { rows, total };
});

const departmentChart = computed(() => {
    const rows = (distribution.value?.by_department ?? []).map((item) => ({
        label: item.department,
        count: item.count,
    }));

    const total = rows.reduce((sum, row) => sum + row.count, 0);
    const max = Math.max(1, ...rows.map((row) => row.count));

    return { rows, total, max };
});

function barWidth(count, total) {
    if (total === 0) return 0;
    return Math.max(4, Math.round((count / total) * 100));
}

function departmentWidth(count, max) {
    if (max === 0) return 0;
    return Math.max(4, Math.round((count / max) * 100));
}

async function loadDashboard() {
    loading.value = true;
    error.value = null;

    try {
        const [metricsRes, distRes] = await Promise.all([
            http.get('/dashboard/metrics'),
            http.get('/dashboard/distribution'),
            sla.fetchAll(),
        ]);

        metrics.value = metricsRes.data.metrics;
        distribution.value = distRes.data.distribution;
    } catch (err) {
        error.value = err.response?.data?.message ?? 'No se pudieron cargar las métricas.';
    } finally {
        loading.value = false;
    }
}

/*
 * Guarda los umbrales ajustados de una regla SLA.
 */
async function saveSlaRule(rule) {
    savingRule.value = rule.id;

    try {
        await sla.updateRule(rule);
    } catch (err) {
        error.value = err.response?.data?.message ?? 'No se pudo actualizar la regla SLA.';
    } finally {
        savingRule.value = null;
    }
}

onMounted(loadDashboard);
</script>

<template>
    <section class="mx-auto w-full max-w-6xl">
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Métricas Globales</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Indicadores de rendimiento y carga de trabajo del centro de soporte.
                </p>
            </div>

            <button
                type="button"
                @click="showAuditLog = true"
                class="inline-flex items-center gap-2 rounded-2xl bg-teal-800 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-800/40"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M3 5.5A1.5 1.5 0 0 1 4.5 4h11A1.5 1.5 0 0 1 17 5.5v9A1.5 1.5 0 0 1 15.5 16h-11A1.5 1.5 0 0 1 3 14.5v-9Zm5.5.75a2.25 2.25 0 0 0-1.75 3.6V11.5a.75.75 0 0 0 1.5 0V9.85a2.25 2.25 0 0 0 .25-3.6ZM7.25 5.5Zm0 2.5h.004v.002H7.25V8h.002Zm6-0.75A.75.75 0 0 0 12.5 7.25v.002A.75.75 0 0 0 13.25 8h.002a.75.75 0 0 0 .75-.75V7.25a.75.75 0 0 0-.75-.75h-.002Zm.75 2.75a.75.75 0 0 0-1.5 0V12a.75.75 0 0 0 1.5 0V9.5Zm-4.5 0a.75.75 0 0 0-1.5 0V9.5h.002V11a.75.75 0 0 0 1.5 0V9.5Z" clip-rule="evenodd" />
                </svg>
                Bitácora de auditoría
            </button>
        </div>

        <div v-if="loading" class="rounded-3xl bg-white p-16 text-center ring-1 ring-slate-200/70">
            <p class="text-sm text-slate-500">Cargando métricas…</p>
        </div>

        <div v-else-if="error" class="rounded-3xl bg-white p-16 text-center ring-1 ring-slate-200/70">
            <p class="text-sm font-medium text-red-600">{{ error }}</p>
            <button
                type="button"
                @click="loadDashboard"
                class="mt-4 rounded-xl bg-teal-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-teal-700"
            >
                Reintentar
            </button>
        </div>

        <div v-else class="space-y-6">
            <!-- Tarjetas de KPIs -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-3xl bg-white p-5 ring-1 ring-slate-200/70">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Abiertos</span>
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
                            <svg class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 1a6 6 0 0 0-3.815 10.631C7.237 12.5 8 13.443 8 14.456v.644a.75.75 0 0 0 .572.729 6.016 6.016 0 0 0 2.856 0A.75.75 0 0 0 12 15.1v-.644c0-1.013.762-1.957 1.815-2.825A6 6 0 0 0 10 1ZM5.5 7a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0Z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-3 text-3xl font-black text-slate-900">{{ metrics.open_tickets }}</p>
                    <p class="mt-1 text-sm text-slate-500">de {{ metrics.total_tickets }} totales</p>
                </div>

                <div class="rounded-3xl bg-white p-5 ring-1 ring-slate-200/70">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Tiempo medio</span>
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-teal-50 text-teal-600">
                            <svg class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13.25a.75.75 0 0 0-1.5 0v4.94l3.03 1.75a.75.75 0 0 0 .75-1.3l-2.28-1.32V4.75Z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-3 text-3xl font-black text-slate-900">{{ formatSeconds(metrics.avg_response_time_seconds) }}</p>
                    <p class="mt-1 text-sm text-slate-500">primera respuesta</p>
                </div>

                <div class="rounded-3xl bg-white p-5 ring-1 ring-slate-200/70">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Críticos sin asignar</span>
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                            <svg class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </div>
                    <p :class="metrics.critical_unassigned > 0 ? 'text-amber-600' : 'text-slate-900'" class="mt-3 text-3xl font-black">
                        {{ metrics.critical_unassigned }}
                    </p>
                    <p class="mt-1 text-sm text-slate-500">prioridad alta o urgente</p>
                </div>

                <div class="rounded-3xl bg-white p-5 ring-1 ring-slate-200/70">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Satisfacción</span>
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                            <svg class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 2a6 6 0 0 0-6 6v3.586l-.707.707A1 1 0 0 0 4 14h12a1 1 0 0 0 .707-1.707L16 11.586V8a6 6 0 0 0-6-6ZM10 18a3 3 0 0 1-3-3h6a3 3 0 0 1-3 3Z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-3 text-3xl font-black text-slate-900">
                        {{ metrics.satisfaction != null ? `${metrics.satisfaction}%` : '—' }}
                    </p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ metrics.ratings_count }} calificación{{ metrics.ratings_count === 1 ? '' : 'es' }}
                    </p>
                </div>
            </div>

            <!-- Gráficos de carga de trabajo -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="rounded-3xl bg-white p-6 ring-1 ring-slate-200/70">
                    <h3 class="text-base font-bold text-slate-900">Tickets por estado</h3>
                    <p class="mt-0.5 text-sm text-slate-500">Distribución de la carga actual.</p>

                    <div class="mt-6 space-y-4">
                        <div v-for="row in statusChart.rows" :key="row.key" class="flex items-center gap-3">
                            <span class="w-24 shrink-0 text-sm font-medium text-slate-600">{{ row.label }}</span>
                            <div class="h-3 flex-1 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full bg-gradient-to-r from-teal-600 to-cyan-500"
                                    :style="{ width: barWidth(row.count, statusChart.total) + '%' }"
                                ></div>
                            </div>
                            <span class="w-8 text-right text-sm font-bold text-slate-700">{{ row.count }}</span>
                        </div>
                        <p v-if="statusChart.rows.length === 0" class="text-sm text-slate-400">Sin tickets registrados.</p>
                    </div>
                </div>

                <div class="rounded-3xl bg-white p-6 ring-1 ring-slate-200/70">
                    <h3 class="text-base font-bold text-slate-900">Tickets por departamento</h3>
                    <p class="mt-0.5 text-sm text-slate-500">Carga asignada a cada equipo.</p>

                    <div class="mt-6 space-y-4">
                        <div
                            v-for="row in departmentChart.rows"
                            :key="row.label"
                            class="flex items-center gap-3"
                        >
                            <span class="w-28 shrink-0 truncate text-sm font-medium text-slate-600">{{ row.label }}</span>
                            <div class="h-3 flex-1 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full bg-gradient-to-r from-teal-600 to-cyan-500"
                                    :style="{ width: departmentWidth(row.count, departmentChart.max) + '%' }"
                                ></div>
                            </div>
                            <span class="w-8 text-right text-sm font-bold text-slate-700">{{ row.count }}</span>
                        </div>
                        <p v-if="departmentChart.rows.length === 0" class="text-sm text-slate-400">Sin departamentos activos.</p>
                    </div>
                </div>
            </div>

            <!-- Tiempo de resolución -->
            <div class="rounded-3xl bg-white p-6 ring-1 ring-slate-200/70">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Tiempo promedio de resolución</h3>
                        <p class="mt-0.5 text-sm text-slate-500">Desde la apertura hasta el cierre del ticket.</p>
                    </div>
                    <p class="text-2xl font-black text-slate-900">{{ formatSeconds(metrics.avg_resolution_time_seconds) }}</p>
                </div>
            </div>

            <!-- Cumplimiento de SLA -->
            <div class="rounded-3xl bg-white p-6 ring-1 ring-slate-200/70">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Cumplimiento de SLA</h3>
                        <p class="mt-0.5 text-sm text-slate-500">
                            Tickets abiertos/en proceso que excedieron el tiempo límite según su prioridad.
                        </p>
                    </div>

                    <div class="flex items-center gap-1.5">
                        <template v-if="sla.status?.compliance_percent != null">
                            <span class="text-2xl font-black text-slate-900">{{ sla.status.compliance_percent }}%</span>
                            <span class="text-xs font-medium uppercase tracking-wide text-slate-400">cumplimiento</span>
                        </template>
                        <span v-else class="text-sm text-slate-400">Sin tickets activos</span>
                    </div>
                </div>

                <!-- Resumen de incumplimientos -->
                <div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Activos</p>
                        <p class="mt-1 text-2xl font-black text-slate-900">{{ sla.status?.total_tickets ?? 0 }}</p>
                    </div>
                    <div class="rounded-2xl bg-emerald-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700/60">Dentro del acuerdo</p>
                        <p class="mt-1 text-2xl font-black text-emerald-700">{{ sla.status?.compliant ?? 0 }}</p>
                    </div>
                    <div class="rounded-2xl bg-red-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-red-700/60">SLA violado</p>
                        <p class="mt-1 text-2xl font-black text-red-700">{{ sla.status?.breached ?? 0 }}</p>
                    </div>
                </div>

                <!-- Umbrales configurables por prioridad -->
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Umbrales por prioridad (horas)</h4>
                <div class="mt-3 divide-y divide-slate-100">
                    <div
                        v-for="rule in sla.rules"
                        :key="rule.id"
                        class="flex flex-wrap items-center gap-3 py-3"
                    >
                        <span
                            :class="priorityOptions[rule.priority]?.badge"
                            class="w-24 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                        >
                            {{ slaPriorityLabel(rule.priority) }}
                        </span>

                        <label class="flex items-center gap-1.5 text-xs text-slate-500">
                            Respuesta
                            <input
                                v-model.number="rule.response_hours"
                                type="number"
                                min="1"
                                class="w-20 rounded-xl border-0 bg-slate-50 px-2 py-1.5 text-sm text-slate-700 ring-1 ring-slate-200 focus:ring-2 focus:ring-teal-800/40"
                            />
                        </label>

                        <label class="flex items-center gap-1.5 text-xs text-slate-500">
                            Resolución
                            <input
                                v-model.number="rule.resolution_hours"
                                type="number"
                                min="1"
                                class="w-20 rounded-xl border-0 bg-slate-50 px-2 py-1.5 text-sm text-slate-700 ring-1 ring-slate-200 focus:ring-2 focus:ring-teal-800/40"
                            />
                        </label>

                        <button
                            type="button"
                            :disabled="savingRule === rule.id"
                            @click="saveSlaRule(rule)"
                            class="ml-auto rounded-xl bg-teal-800 px-4 py-2 text-xs font-medium text-white transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            {{ savingRule === rule.id ? 'Guardando…' : 'Guardar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <AuditLogModal v-if="showAuditLog" @close="showAuditLog = false" />
    </section>
</template>
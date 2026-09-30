<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';

import TicketFormModal from '../../components/TicketFormModal.vue';
import { useTicketsStore } from '../../stores/tickets';
import { priorityOptions, statusOptions } from '../../utils/ticket-options';

const router = useRouter();

const store = useTicketsStore();
const { filteredTickets, countByStatus, countByPriority, loading, error, activeStatus, activePriority } = storeToRefs(store);

const statusTabs = [
    { key: 'all', label: 'Todos' },
    { key: 'open', label: 'Abiertos' },
    { key: 'in_progress', label: 'En Proceso' },
    { key: 'resolved', label: 'Resueltos' },
    { key: 'closed', label: 'Cerrados' },
];

const priorityFilters = [
    { key: 'all', label: 'Todas' },
    { key: 'urgent', label: 'Urgente' },
    { key: 'high', label: 'Alta' },
    { key: 'medium', label: 'Media' },
    { key: 'low', label: 'Baja' },
];

const showModal = ref(false);

function handleCreated() {
    showModal.value = false;
    store.fetchTickets();
}

function openTicket(id) {
    router.push({ name: 'ticket-detail', params: { id } });
}

onMounted(() => store.fetchTickets());
</script>

<template>
    <section class="mx-auto w-full max-w-6xl">
        <!-- Cabecera: título y acción principal -->
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Bandeja de Tickets</h1>
                <p class="mt-1 text-sm text-slate-500">Gestiona y da seguimiento a las incidencias de soporte.</p>
            </div>

            <button
                type="button"
                @click="showModal = true"
                class="inline-flex items-center gap-2 rounded-2xl bg-teal-800 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-800/40"
            >
                <span class="text-base leading-none">+</span>
                Nuevo Ticket
            </button>
        </div>

        <!-- Filtros rápidos: estado y prioridad -->
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-1 rounded-2xl bg-white p-1.5 ring-1 ring-slate-200/70">
                <button
                    v-for="tab in statusTabs"
                    :key="tab.key"
                    type="button"
                    @click="activeStatus = tab.key"
                    :class="activeStatus === tab.key
                        ? 'bg-slate-900 text-white shadow-sm'
                        : 'text-slate-500 hover:text-slate-700'"
                    class="rounded-xl px-3.5 py-1.5 text-sm font-medium transition"
                >
                    {{ tab.label }}
                    <span class="ml-1 text-xs opacity-70">{{ countByStatus(tab.key) }}</span>
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-1 rounded-2xl bg-white p-1.5 ring-1 ring-slate-200/70">
                <button
                    v-for="filter in priorityFilters"
                    :key="filter.key"
                    type="button"
                    @click="activePriority = filter.key"
                    :class="activePriority === filter.key
                        ? 'bg-teal-800 text-white shadow-sm'
                        : 'text-slate-500 hover:text-slate-700'"
                    class="rounded-xl px-3 py-1.5 text-sm font-medium transition"
                >
                    {{ filter.label }}
                    <span class="ml-1 text-xs opacity-70">{{ countByPriority(filter.key) }}</span>
                </button>
            </div>
        </div>

        <!-- Estados de carga / error -->
        <div v-if="loading" class="rounded-3xl bg-white p-16 text-center ring-1 ring-slate-200/70">
            <p class="text-sm text-slate-500">Cargando tickets…</p>
        </div>

        <div v-else-if="error" class="rounded-3xl bg-white p-16 text-center ring-1 ring-slate-200/70">
            <p class="text-sm font-medium text-red-600">{{ error }}</p>
            <button
                type="button"
                @click="fetchTickets"
                class="mt-4 rounded-xl bg-teal-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-teal-700"
            >
                Reintentar
            </button>
        </div>

        <!-- Listado de tickets -->
        <div v-else class="overflow-hidden rounded-3xl bg-white ring-1 ring-slate-200/70 shadow-sm">
            <div class="hidden grid-cols-12 gap-4 border-b border-slate-100 px-6 py-3 text-xs font-medium uppercase tracking-wide text-slate-400 sm:grid">
                <span class="col-span-1">ID</span>
                <span class="col-span-4">Asunto</span>
                <span class="col-span-2">Departamento</span>
                <span class="col-span-2">Prioridad</span>
                <span class="col-span-1">Estado</span>
                <span class="col-span-2">Asignado a</span>
            </div>

            <ul v-if="filteredTickets.length" class="divide-y divide-slate-100">
                <li
                    v-for="ticket in filteredTickets"
                    :key="ticket.id"
                    @click="openTicket(ticket.id)"
                    class="grid cursor-pointer grid-cols-2 items-center gap-3 px-6 py-4 transition hover:bg-slate-50 sm:grid-cols-12 sm:gap-4"
                >
                    <span class="col-span-1 hidden font-mono text-xs text-slate-400 sm:block">#{{ ticket.id }}</span>

                    <div class="col-span-2 sm:col-span-4 sm:pr-4">
                        <div class="flex items-center gap-2">
                            <p class="truncate text-sm font-semibold text-slate-700">{{ ticket.title }}</p>
                            <span
                                v-if="ticket.sla_violated"
                                title="Este ticket superó el tiempo límite de atención (SLA)"
                                class="inline-flex shrink-0 items-center rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-red-700 ring-1 ring-inset ring-red-600/20"
                            >
                                SLA Violado
                            </span>
                        </div>
                        <p class="truncate text-sm text-slate-500">{{ ticket.description }}</p>
                    </div>

                    <span class="col-span-1 text-sm text-slate-500 sm:col-span-2">
                        {{ ticket.department?.name ?? '—' }}
                    </span>

                    <span class="col-span-1 sm:col-span-2">
                        <span
                            :class="priorityOptions[ticket.priority]?.badge"
                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                        >
                            {{ priorityOptions[ticket.priority]?.label ?? ticket.priority }}
                        </span>
                    </span>

                    <span class="col-span-1 sm:col-span-1">
                        <span
                            :class="statusOptions[ticket.status]?.badge"
                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                        >
                            {{ statusOptions[ticket.status]?.label ?? ticket.status }}
                        </span>
                    </span>

                    <p class="text-right text-sm text-slate-500 sm:col-span-2 sm:text-left">
                        <template v-if="ticket.assignedAgent">
                            <span class="hidden sm:inline">{{ ticket.assignedAgent.name }}</span>
                            <span class="sm:hidden">{{ ticket.assignedAgent.name.split(' ')[0] }}</span>
                        </template>
                        <span v-else class="italic">En cola</span>
                    </p>
                </li>
            </ul>

            <div v-else class="p-16 text-center">
                <p class="text-sm font-medium text-slate-700">No hay tickets en esta cola</p>
                <p class="mt-1 text-sm text-slate-500">Prueba con otro filtro o crea un ticket nuevo.</p>
            </div>
        </div>

        <!-- Modal de creación -->
        <TicketFormModal v-if="showModal" @close="showModal = false" @created="handleCreated" />
    </section>
</template>
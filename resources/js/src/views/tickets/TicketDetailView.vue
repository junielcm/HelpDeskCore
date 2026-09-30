<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import http from '../../services/axios';
import { useAuthStore } from '../../stores/auth';
import { isStaffRole, priorityOptions, statusOptions } from '../../utils/ticket-options';

const route = useRoute();
const router = useRouter();

const auth = useAuthStore();

const ticket = ref(null);
const loading = ref(false);
const error = ref(null);
const saving = ref(false);

/*
 * Solo el staff (agente, supervisor, admin) puede cambiar el estado, ver
 * notas internas y redactar notas internas para el resto del equipo.
 */
const isStaff = computed(() => isStaffRole(auth.user?.role?.name));

/*
 * La gestión operativa (supervisor/admin) es la única que puede editar la
 * prioridad y reasignar tickets, según el protocolo de edición de tickets.
 */
const isManager = computed(() => ['supervisor', 'admin'].includes(auth.user?.role?.name));

/*
 * Un agente puede tomar para sí mismo un ticket en cola de su departamento.
 */
const isAgent = computed(() => auth.user?.role?.name === 'agent');

/*
 * El agente que actualmente tiene asignado el ticket es el único (además de
 * supervisor/admin) que puede resolverlo o cerrarlo.
 */
const isAssignee = computed(() => Number(ticket.value?.assigned_to) === Number(auth.user?.id));

/*
 * El ticket está en cola y el agente autenticado puede tomarlo.
 */
const canTakeTicket = computed(() => {
    return isAgent.value && ticket.value && !ticket.value.assigned_to;
});

/*
 * Puede cerrar/resolver el ticket quien lo tiene asignado o pertenece a la
 * gestión operativa, siempre que aún no esté finalizado.
 */
const canCloseTicket = computed(() => {
    return ticket.value && !['resolved', 'closed'].includes(ticket.value.status)
        && (isAssignee.value || isManager.value);
});

/*
 * Agentes activos del departamento del ticket, disponibles para reasignación.
 * Solo se cargan para la gestión operativa.
 */
const assignableAgents = ref([]);
const loadingAgents = ref(false);

/*
 * URL base de archivos adjuntos. Los adjuntos viven en el disco público
 * (storage/app/public/tickets/...) y se sirven en /storage/...
 */
const storageBase = `${window.location.origin}/storage`;

const comments = computed(() => {
    if (!ticket.value?.comments) {
        return [];
    }

    return [...ticket.value.comments].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
});

const reply = reactive({
    body: '',
    is_internal: false,
    attachment: null,
});

const attachmentInput = ref(null);
const threadRef = ref(null);

async function fetchTicket() {
    loading.value = true;
    error.value = null;

    try {
        const { data } = await http.get(`/tickets/${route.params.id}`);
        ticket.value = data.ticket;
    } catch (err) {
        error.value = err.response?.data?.message ?? 'No se pudo cargar el ticket.';
    } finally {
        loading.value = false;
    }
}

async function fetchAssignableAgents() {
    loadingAgents.value = true;

    try {
        const { data } = await http.get(`/tickets/${route.params.id}/agents`);
        assignableAgents.value = data.agents;
    } catch (err) {
        error.value = err.response?.data?.message ?? 'No se pudieron cargar los agentes disponibles.';
    } finally {
        loadingAgents.value = false;
    }
}

function goBack() {
    router.push({ name: 'tickets' });
}

/*
 * Actualización rápida de estado/prioridad desde la cabecera. Si el servidor
 * rechaza el cambio, se restaura el valor anterior en la interfaz.
 */
async function updateField(field, value) {
    const previous = ticket.value[field];
    ticket.value[field] = value;

    try {
        await http.put(`/tickets/${ticket.value.id}`, { [field]: value });
    } catch (err) {
        ticket.value[field] = previous;
        error.value = err.response?.data?.message ?? 'No se pudo actualizar el ticket.';
    }
}

/*
 * Reasignación del ticket a un agente del departamento (o vuelta a la cola).
 * Como afecta a la relación assignedAgent, se recarga el detalle completo.
 */
async function reassign(value) {
    const target = value === '' ? null : Number(value);

    if (target === ticket.value.assigned_to) {
        return;
    }

    saving.value = true;
    error.value = null;

    try {
        await http.put(`/tickets/${ticket.value.id}`, { assigned_to: target });
        await fetchTicket();
    } catch (err) {
        await fetchTicket();
        error.value = err.response?.data?.message ?? 'No se pudo reasignar el ticket.';
    } finally {
        saving.value = false;
    }
}

/*
 * Un agente toma para sí mismo un ticket en cola: se autoasigna y lo pasa a
 * "en proceso" en una sola operación.
 */
async function takeTicket() {
    saving.value = true;
    error.value = null;

    try {
        await http.put(`/tickets/${ticket.value.id}`, {
            assigned_to: auth.user.id,
            status: 'in_progress',
        });
        await fetchTicket();
    } catch (err) {
        error.value = err.response?.data?.message ?? 'No se pudo tomar el ticket.';
    } finally {
        saving.value = false;
    }
}

/*
 * Resolución o cierre del ticket por el agente asignado (o gestión operativa).
 * Se usa un diálogo de confirmación para evitar cierres accidentales.
 */
async function closeTicket(status) {
    const label = statusOptions[status]?.label ?? status;

    if (!confirm(`¿Marcar el ticket como "${label}"?`)) {
        return;
    }

    saving.value = true;
    error.value = null;

    try {
        await http.put(`/tickets/${ticket.value.id}`, { status });
        await fetchTicket();
    } catch (err) {
        error.value = err.response?.data?.message ?? 'No se pudo actualizar el ticket.';
    } finally {
        saving.value = false;
    }
}

async function submitReply() {
    const body = reply.body.trim();

    if (!body) {
        return;
    }

    saving.value = true;
    error.value = null;

    try {
        const payload = new FormData();
        payload.append('body', body);
        payload.append('is_internal', reply.is_internal ? '1' : '0');

        if (reply.attachment) {
            payload.append('attachment', reply.attachment);
        }

        const { data } = await http.post(`/tickets/${ticket.value.id}/comments`, payload);

        // Aparece la respuesta al instante con el comentario devuelto por el
        // servidor, sin depender de otro request ni de recargar la página.
        if (data.comment) {
            ticket.value.comments = [data.comment, ...(ticket.value.comments ?? [])];

            await nextTick();

            appendToThread();
        }

        reply.body = '';
        reply.is_internal = false;
        reply.attachment = null;

        if (attachmentInput.value) {
            attachmentInput.value.value = '';
        }

        // Refresco silencioso en segundo plano para sincronizar el estado del
        // ticket (ej. contador, adjuntos) sin interrumpir la conversación.
        refreshTicket();
    } catch (err) {
        error.value = err.response?.data?.message ?? 'No se pudo enviar el mensaje.';
    } finally {
        saving.value = false;
    }
}

/*
 * Lleva la vista hasta la última respuesta recién publicada.
 */
function appendToThread() {
    const lastMessage = threadRef.value?.lastElementChild;

    if (lastMessage?.scrollIntoView) {
        lastMessage.scrollIntoView({ behavior: 'smooth', block: 'end' });
    }
}

/*
 * Recupera el detalle del ticket en segundo plano, sin tocar la pantalla
 * de carga ni bloquear lo que el usuario está viendo.
 */
async function refreshTicket() {
    try {
        const { data } = await http.get(`/tickets/${route.params.id}`);
        ticket.value = data.ticket;
    } catch {
        // Silencioso: la respuesta ya quedó visible con el update optimista.
    }
}

function attachmentUrl(attachment) {
    return `${storageBase}/${attachment.file_path}`;
}

function formatDate(value) {
    return new Date(value).toLocaleString('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function initials(name) {
    return name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function isClientAuthor(comment) {
    return comment.user?.role?.name === 'client';
}

onMounted(() => {
    fetchTicket();

    if (isManager.value) {
        fetchAssignableAgents();
    }
});
</script>

<template>
    <section class="mx-auto w-full max-w-6xl">
        <!-- Cabecera: volver + identificación del ticket -->
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    @click="goBack"
                    class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-medium text-slate-500 transition hover:bg-white hover:text-slate-900"
                >
                    <span class="text-base leading-none">←</span>
                    Volver a la bandeja
                </button>

                <div class="h-6 w-px bg-slate-200"></div>

                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">
                            #{{ ticket?.id }} · {{ ticket?.title }}
                        </h1>
                        <span
                            v-if="ticket?.sla_violated"
                            title="Este ticket superó el tiempo límite de atención (SLA)"
                            class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-red-700 ring-1 ring-inset ring-red-600/20"
                        >
                            SLA Violado
                        </span>
                    </div>
                    <p v-if="ticket" class="mt-0.5 text-sm text-slate-500">
                        Creado el {{ formatDate(ticket.created_at) }}
                    </p>
                </div>
            </div>

            <!-- Selectores rápidos según el rol del usuario -->
            <div v-if="isStaff && ticket" class="flex flex-wrap items-center gap-3">
                <!-- Estado: actualizable por todo el staff -->
                <label class="flex flex-col gap-1 text-xs font-medium uppercase tracking-wide text-slate-400">
                    Estado
                    <select
                        :value="ticket.status"
                        :disabled="saving"
                        @change="updateField('status', $event.target.value)"
                        class="w-40 rounded-xl border-0 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 transition focus:ring-2 focus:ring-teal-800/40 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <option v-for="(opt, key) in statusOptions" :key="key" :value="key">
                            {{ opt.label }}
                        </option>
                    </select>
                </label>

                <!-- Prioridad: edición operativa restringida a supervisor/admin -->
                <label v-if="isManager" class="flex flex-col gap-1 text-xs font-medium uppercase tracking-wide text-slate-400">
                    Prioridad
                    <select
                        :value="ticket.priority"
                        :disabled="saving"
                        @change="updateField('priority', $event.target.value)"
                        class="w-40 rounded-xl border-0 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 transition focus:ring-2 focus:ring-teal-800/40 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <option v-for="(opt, key) in priorityOptions" :key="key" :value="key">
                            {{ opt.label }}
                        </option>
                    </select>
                </label>

                <!-- Reasignación: reservada a supervisor/admin -->
                <label v-if="isManager" class="flex flex-col gap-1 text-xs font-medium uppercase tracking-wide text-slate-400">
                    Asignar a
                    <select
                        :value="ticket.assigned_to ?? ''"
                        :disabled="saving || loadingAgents"
                        @change="reassign($event.target.value)"
                        class="w-44 rounded-xl border-0 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 transition focus:ring-2 focus:ring-teal-800/40 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <option value="">En cola (sin asignar)</option>
                        <option v-for="agent in assignableAgents" :key="agent.id" :value="agent.id">
                            {{ agent.name }}
                        </option>
                    </select>
                </label>

                <!-- Tomar ticket: autoasignación de un agente para trabajar la incidencia -->
                <button
                    v-if="canTakeTicket"
                    type="button"
                    :disabled="saving"
                    @click="takeTicket"
                    class="flex items-center gap-2 rounded-xl bg-teal-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm ring-1 ring-teal-900/10 transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M10 2a7 7 0 1 0 0 14 7 7 0 0 0 0-14Zm-1 4a1 1 0 1 1 2 0v3.6l2.1 1.25a1 1 0 1 1-.9 1.5L9.8 11.5A1 1 0 0 1 9 10.6V6Z" fill-rule="evenodd" clip-rule="evenodd" />
                    </svg>
                    Tomar ticket
                </button>

                <!-- Resolver / Cerrar: el agente asignado (o gestión operativa) finaliza el ticket -->
                <template v-if="canCloseTicket">
                    <button
                        type="button"
                        :disabled="saving"
                        @click="closeTicket('resolved')"
                        class="flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm ring-1 ring-emerald-900/10 transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M10 2a7 7 0 1 0 0 14 7 7 0 0 0 0-14Zm3.5 4.7-4.2 4.2-1.8-1.8a.75.75 0 1 0-1.06 1.06l2.3 2.3a.75.75 0 0 0 1.06 0l4.75-4.75a.75.75 0 1 0-1.06-1.06Z" fill-rule="evenodd" clip-rule="evenodd" />
                        </svg>
                        Resolver
                    </button>
                    <button
                        type="button"
                        :disabled="saving"
                        @click="closeTicket('closed')"
                        class="flex items-center gap-2 rounded-xl bg-slate-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm ring-1 ring-slate-900/10 transition hover:bg-slate-600 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 2a7 7 0 1 0 0 14 7 7 0 0 0 0-14Zm3.36 5.2a.75.75 0 0 0-1.06-1.06L9 9.44l-1.3-1.3a.75.75 0 0 0-1.06 1.06l1.83 1.83a.75.75 0 0 0 1.06 0l3.83-3.83Z" clip-rule="evenodd" />
                        </svg>
                        Cerrar
                    </button>
                </template>
            </div>
        </div>

        <!-- Estados de carga / error globales -->
        <div v-if="loading" class="rounded-3xl bg-white p-16 text-center ring-1 ring-slate-200/70">
            <p class="text-sm text-slate-500">Cargando ticket…</p>
        </div>

        <div v-else-if="error" class="rounded-3xl bg-white p-16 text-center ring-1 ring-slate-200/70">
            <p class="text-sm font-medium text-red-600">{{ error }}</p>
            <button
                type="button"
                @click="fetchTicket"
                class="mt-4 rounded-xl bg-teal-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-teal-700"
            >
                Reintentar
            </button>
        </div>

        <template v-else-if="ticket">
            <div class="grid gap-6 lg:grid-cols-[300px_1fr]">
                <!-- Panel izquierdo: información general -->
                <aside class="h-fit rounded-3xl bg-white p-6 ring-1 ring-slate-200/70 shadow-sm">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Información general</h2>

                    <dl class="mt-5 space-y-4 text-sm">
                        <div>
                            <dt class="text-slate-400">Cliente</dt>
                            <dd class="mt-0.5 font-medium text-slate-700">{{ ticket.client?.name ?? '—' }}</dd>
                        </div>

                        <div>
                            <dt class="text-slate-400">Departamento</dt>
                            <dd class="mt-0.5 font-medium text-slate-700">{{ ticket.department?.name ?? '—' }}</dd>
                        </div>

                        <div>
                            <dt class="text-slate-400">Estado</dt>
                            <dd class="mt-1">
                                <span
                                    :class="statusOptions[ticket.status]?.badge"
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                >
                                    {{ statusOptions[ticket.status]?.label ?? ticket.status }}
                                </span>
                            </dd>
                        </div>

                        <div>
                            <dt class="text-slate-400">Prioridad</dt>
                            <dd class="mt-1">
                                <span
                                    :class="priorityOptions[ticket.priority]?.badge"
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                >
                                    {{ priorityOptions[ticket.priority]?.label ?? ticket.priority }}
                                </span>
                            </dd>
                        </div>

                        <div>
                            <dt class="text-slate-400">Agente asignado</dt>
                            <dd class="mt-0.5 font-medium text-slate-700">
                                {{ ticket.assignedAgent?.name ?? 'En cola' }}
                            </dd>
                        </div>
                    </dl>

                    <!-- Descripción original de la solicitud -->
                    <div class="mt-6 border-t border-slate-100 pt-5">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Descripción</h3>
                        <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-600">
                            {{ ticket.description }}
                        </p>
                    </div>

                    <!-- Adjuntos a nivel de ticket -->
                    <div v-if="ticket.attachments?.length" class="mt-6 border-t border-slate-100 pt-5">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Adjuntos del ticket</h3>
                        <ul class="mt-3 space-y-2">
                            <li v-for="attachment in ticket.attachments" :key="attachment.id">
                                <a
                                    :href="attachmentUrl(attachment)"
                                    :download="attachment.file_name"
                                    class="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-xs font-medium text-teal-800 transition hover:bg-teal-800 hover:text-white"
                                >
                                    <span class="text-base leading-none">↓</span>
                                    <span class="min-w-0 truncate">{{ attachment.file_name }}</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </aside>

                <!-- Hilo de conversación -->
                <div class="flex flex-col overflow-hidden rounded-3xl bg-white ring-1 ring-slate-200/70 shadow-sm">
                    <div class="border-b border-slate-100 px-6 py-4">
                        <h2 class="text-sm font-semibold text-slate-900">
                            Conversación
                            <span class="ml-1 font-normal text-slate-400">({{ comments.length }})</span>
                        </h2>
                    </div>

                    <!-- Mensajes -->
                    <ul ref="threadRef" class="flex-1 space-y-5 p-6">
                        <li v-for="comment in comments" :key="comment.id">
                            <article
                                :class="comment.is_internal
                                    ? 'border border-amber-200 bg-amber-50'
                                    : 'bg-slate-50'"
                                class="rounded-2xl p-5"
                            >
                                <!-- Cabecera del mensaje -->
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <span
                                            :class="isClientAuthor(comment)
                                                ? 'bg-slate-400'
                                                : 'bg-teal-800'"
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                                        >
                                            {{ initials(comment.user?.name ?? '?') }}
                                        </span>

                                        <div>
                                            <p class="text-sm font-semibold text-slate-800">
                                                {{ comment.user?.name ?? 'Usuario' }}
                                            </p>
                                            <p class="text-xs text-slate-400">{{ formatDate(comment.created_at) }}</p>
                                        </div>
                                    </div>

                                    <!-- Etiqueta de nota interna (sólo visible para el staff) -->
                                    <span
                                        v-if="comment.is_internal && isStaff"
                                        class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-amber-800"
                                    >
                                        Nota Interna (Privada)
                                    </span>
                                </div>

                                <!-- Cuerpo del mensaje -->
                                <p class="mt-4 whitespace-pre-line text-sm leading-relaxed text-slate-700">
                                    {{ comment.body }}
                                </p>

                                <!-- Adjuntos del comentario -->
                                <ul v-if="comment.attachments?.length" class="mt-4 flex flex-wrap gap-2">
                                    <li v-for="attachment in comment.attachments" :key="attachment.id">
                                        <a
                                            :href="attachmentUrl(attachment)"
                                            :download="attachment.file_name"
                                            class="flex items-center gap-1.5 rounded-xl bg-white px-3 py-1.5 text-xs font-medium text-teal-800 ring-1 ring-slate-200 transition hover:bg-teal-800 hover:text-white"
                                        >
                                            <span class="text-sm leading-none">↓</span>
                                            <span class="max-w-40 truncate">{{ attachment.file_name }}</span>
                                        </a>
                                    </li>
                                </ul>
                            </article>
                        </li>
                    </ul>

                    <!-- Formulario de respuesta -->
                    <form
                        @submit.prevent="submitReply"
                        class="border-t border-slate-100 bg-slate-50/60 p-6"
                    >
                        <textarea
                            v-model="reply.body"
                            rows="3"
                            required
                            placeholder="Escribe un mensaje…"
                            class="w-full resize-none rounded-2xl border-0 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm ring-1 ring-slate-200 transition placeholder:text-slate-400 focus:ring-2 focus:ring-teal-800/40"
                        ></textarea>

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-4">
                                <!-- Toggle de nota interna, restringido al staff -->
                                <label v-if="isStaff" class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                                    <input
                                        v-model="reply.is_internal"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300 text-teal-800 focus:ring-teal-800/40"
                                    />
                                    Nota interna (privada)
                                </label>

                                <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                                    <input
                                        ref="attachmentInput"
                                        @change="reply.attachment = $event.target.files[0] ?? null"
                                        type="file"
                                        class="block w-56 text-xs text-slate-500 file:mr-3 file:rounded-xl file:border-0 file:bg-white file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-teal-800 file:shadow-sm file:ring-1 file:ring-slate-200 file:transition hover:file:bg-teal-50"
                                    />
                                </label>
                            </div>

                            <button
                                type="submit"
                                :disabled="saving || !reply.body.trim()"
                                class="rounded-2xl bg-teal-800 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                {{ saving ? 'Enviando…' : 'Enviar mensaje' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </section>
</template>
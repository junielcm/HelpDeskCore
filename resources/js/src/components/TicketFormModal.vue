<script setup>
import { onMounted, reactive, ref } from 'vue';

import http from '../services/axios';

const emit = defineEmits(['close', 'created']);

const departments = ref([]);
const loading = ref(false);
const error = ref(null);

const form = reactive({
    title: '',
    description: '',
    department_id: '',
    priority: 'medium',
    attachment: null,
});

const priorities = [
    { value: 'low', label: 'Baja' },
    { value: 'medium', label: 'Media' },
    { value: 'high', label: 'Alta' },
    { value: 'urgent', label: 'Urgente' },
];

onMounted(async () => {
    try {
        const { data } = await http.get('/departments');

        departments.value = data.departments;
    } catch (err) {
        error.value = 'No se pudieron cargar los departamentos.';
    }
});

async function handleSubmit() {
    loading.value = true;
    error.value = null;

    try {
        // FormData: cuando existe archivo adjunto, axios deja que el navegador
        // fije el Content-Type multipart con su boundary automáticamente.
        const payload = new FormData();
        payload.append('title', form.title);
        payload.append('description', form.description);
        payload.append('department_id', form.department_id);
        payload.append('priority', form.priority);

        if (form.attachment) {
            payload.append('attachment', form.attachment);
        }

        await http.post('/tickets', payload);

        emit('created');
    } catch (err) {
        error.value = err.response?.data?.errors?.title?.[0]
            ?? err.response?.data?.message
            ?? 'No se pudo crear el ticket.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm">
            <div class="w-full max-w-2xl rounded-3xl bg-white p-6 shadow-xl sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold tracking-tight text-slate-900">Nuevo Ticket</h2>
                        <p class="mt-1 text-sm text-slate-500">Describe la incidencia para abrir una solicitud de soporte.</p>
                    </div>

                    <button
                        type="button"
                        @click="emit('close')"
                        aria-label="Cerrar"
                        class="rounded-xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                    >
                        ✕
                    </button>
                </div>

                <form class="mt-6 flex flex-col gap-4" @submit.prevent="handleSubmit">
                    <label class="flex flex-col gap-1.5 text-sm font-medium text-slate-700">
                        Asunto
                        <input
                            v-model.trim="form.title"
                            type="text"
                            required
                            placeholder="Ej. No puedo acceder a mi correo"
                            class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-teal-800 focus:ring-2 focus:ring-teal-800/20"
                        />
                    </label>

                    <label class="flex flex-col gap-1.5 text-sm font-medium text-slate-700">
                        Descripción
                        <textarea
                            v-model.trim="form.description"
                            rows="4"
                            required
                            placeholder="Describe el problema, pasos para reproducirlo, etc."
                            class="resize-none rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-teal-800 focus:ring-2 focus:ring-teal-800/20"
                        />
                    </label>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="flex flex-col gap-1.5 text-sm font-medium text-slate-700">
                            Departamento
                            <select
                                v-model="form.department_id"
                                required
                                class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none transition focus:border-teal-800 focus:ring-2 focus:ring-teal-800/20"
                            >
                                <option value="" disabled>Selecciona…</option>
                                <option v-for="department in departments" :key="department.id" :value="department.id">
                                    {{ department.name }}
                                </option>
                            </select>
                        </label>

                        <label class="flex flex-col gap-1.5 text-sm font-medium text-slate-700">
                            Prioridad
                            <select
                                v-model="form.priority"
                                class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none transition focus:border-teal-800 focus:ring-2 focus:ring-teal-800/20"
                            >
                                <option v-for="priority in priorities" :key="priority.value" :value="priority.value">
                                    {{ priority.label }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <label class="flex flex-col gap-1.5 text-sm font-medium text-slate-700">
                        Adjunto (opcional)
                        <input
                            type="file"
                            accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip"
                            @change="form.attachment = $event.target.files?.[0] ?? null"
                            class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-teal-800 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-white transition hover:border-teal-800/40"
                        />
                    </label>

                    <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

                    <div class="mt-2 flex justify-end gap-3">
                        <button
                            type="button"
                            @click="emit('close')"
                            class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                        >
                            Cancelar
                        </button>

                        <button
                            type="submit"
                            :disabled="loading"
                            class="rounded-xl bg-teal-800 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ loading ? 'Creando…' : 'Crear ticket' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
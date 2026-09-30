<script setup>
import { onMounted, reactive, ref } from 'vue';

import { useDepartmentsStore } from '../../stores/departments';

const store = useDepartmentsStore();

const showModal = ref(false);
const editing = ref(null);
const saving = ref(false);
const formError = ref(null);

const form = reactive({
    name: '',
    description: '',
    is_active: true,
});

function openCreate() {
    editing.value = null;
    formError.value = null;
    Object.assign(form, { name: '', description: '', is_active: true });
    showModal.value = true;
}

function openEdit(department) {
    editing.value = department;
    formError.value = null;
    Object.assign(form, {
        name: department.name,
        description: department.description ?? '',
        is_active: Boolean(department.is_active),
    });
    showModal.value = true;
}

async function handleSubmit() {
    saving.value = true;
    formError.value = null;

    const payload = {
        name: form.name,
        description: form.description || null,
        is_active: form.is_active,
    };

    try {
        if (editing.value) {
            await store.updateDepartment(editing.value.id, payload);
        } else {
            await store.createDepartment(payload);
        }

        showModal.value = false;
    } catch (err) {
        formError.value = store.error ?? 'No se pudo guardar el departamento.';
    } finally {
        saving.value = false;
    }
}

onMounted(() => {
    store.fetchDepartments().catch(() => {});
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Encabezado con acción de alta -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-bold text-teal-700 tracking-wider uppercase">Administración</p>
                <h2 class="text-xl font-black tracking-tight text-slate-900 mt-0.5">Gestión de Departamentos</h2>
                <p class="mt-1 text-sm text-slate-500">Administra las áreas técnicas de atención del HelpDesk.</p>
            </div>

            <button
                type="button"
                @click="openCreate"
                class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-gradient-to-r from-teal-600 to-cyan-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-teal-600/25 transition hover:from-teal-500 hover:to-cyan-400"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M10 3a.75.75 0 0 1 .75.75v5.5h5.5a.75.75 0 0 1 0 1.5h-5.5v5.5a.75.75 0 0 1-1.5 0v-5.5h-5.5a.75.75 0 0 1 0-1.5h5.5v-5.5A.75.75 0 0 1 10 3Z" />
                </svg>
                Nuevo departamento
            </button>
        </div>

        <!-- Tabla de departamentos -->
        <div class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-sm">
            <div v-if="store.loading && store.departments.length === 0" class="p-10 text-center text-sm text-slate-500">
                Cargando departamentos…
            </div>

            <div v-else-if="store.departments.length === 0" class="p-10 text-center text-sm text-slate-500">
                Aún no hay departamentos registrados.
            </div>

            <table v-else class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50/70 text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Departamento</th>
                        <th class="px-5 py-3 font-semibold">Descripción</th>
                        <th class="px-5 py-3 font-semibold">Personal</th>
                        <th class="px-5 py-3 font-semibold">Estatus</th>
                        <th class="px-5 py-3 text-right font-semibold">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="department in store.departments" :key="department.id" class="transition hover:bg-slate-50/60">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tr from-teal-600 to-cyan-500 text-xs font-black text-white shadow-sm">
                                    D
                                </span>
                                <span class="font-semibold text-slate-800">{{ department.name }}</span>
                            </div>
                        </td>
                        <td class="max-w-md truncate px-5 py-3.5 text-slate-600">
                            {{ department.description || '—' }}
                        </td>
                        <td class="px-5 py-3.5 text-slate-600">
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 ring-1 ring-inset ring-slate-500/20">
                                {{ department.users_count }} {{ department.users_count === 1 ? 'miembro' : 'miembros' }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span :class="department.is_active
                                ? 'inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20'
                                : 'inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-500 ring-1 ring-inset ring-slate-500/20'">
                                <span class="h-1.5 w-1.5 rounded-full" :class="department.is_active ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                                {{ department.is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <button
                                type="button"
                                @click="openEdit(department)"
                                class="rounded-lg px-3 py-1.5 text-xs font-semibold text-teal-700 transition hover:bg-teal-50"
                            >
                                Editar
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Modal de alta/edición -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm">
                <div class="w-full max-w-2xl rounded-3xl bg-white p-6 shadow-xl sm:p-8">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-semibold tracking-tight text-slate-900">
                                {{ editing ? 'Editar Departamento' : 'Nuevo Departamento' }}
                            </h3>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ editing ? `Actualizando el área ${editing.name}.` : 'Registra una nueva área técnica de atención.' }}
                            </p>
                        </div>

                        <button
                            type="button"
                            @click="showModal = false"
                            aria-label="Cerrar"
                            class="rounded-xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                        >
                            ✕
                        </button>
                    </div>

                    <form class="mt-6 flex flex-col gap-4" @submit.prevent="handleSubmit">
                        <label class="flex flex-col gap-1.5 text-sm font-medium text-slate-700">
                            Nombre
                            <input
                                v-model.trim="form.name"
                                type="text"
                                required
                                placeholder="Ej. Soporte Técnico"
                                class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-teal-800 focus:ring-2 focus:ring-teal-800/20"
                            />
                        </label>

                        <label class="flex flex-col gap-1.5 text-sm font-medium text-slate-700">
                            Descripción
                            <textarea
                                v-model.trim="form.description"
                                rows="3"
                                placeholder="Describe el alcance del área técnica (opcional)"
                                class="resize-none rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-teal-800 focus:ring-2 focus:ring-teal-800/20"
                            />
                        </label>

                        <label class="flex items-center gap-3 text-sm font-medium text-slate-700">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-teal-700 focus:ring-teal-700"
                            />
                            Departamento activo
                        </label>

                        <p v-if="formError" class="whitespace-pre-line text-sm text-red-600">{{ formError }}</p>

                        <div class="mt-2 flex justify-end gap-3">
                            <button
                                type="button"
                                @click="showModal = false"
                                class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                            >
                                Cancelar
                            </button>

                            <button
                                type="submit"
                                :disabled="saving"
                                class="rounded-xl bg-teal-800 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {{ saving ? 'Guardando…' : editing ? 'Guardar cambios' : 'Crear departamento' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>

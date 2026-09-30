<script setup>
import { computed, onMounted, reactive, ref } from 'vue';

import { useAuthStore } from '../../stores/auth';
import { useUsersStore } from '../../stores/users';

const auth = useAuthStore();
const store = useUsersStore();

const search = ref('');
const showModal = ref(false);
const editing = ref(null);
const saving = ref(false);
const formError = ref(null);

/* Etiquetas legibles de cada rol del sistema. */
const roleLabels = {
    client: 'Cliente',
    agent: 'Agente',
    supervisor: 'Supervisor',
    admin: 'Administrador',
};

/*
 * Roles que el usuario gestiona como permitidos: el supervisor solo puede
 * crear/editar clientes y agentes; el admin puede gestionar todos.
 */
const allowedRoles = computed(() => {
    const isAdmin = auth.user?.role?.name === 'admin';
    return store.roles.filter((role) => isAdmin || ['client', 'agent'].includes(role.name));
});

const roleBadge = (roleName) => ({
    client: 'bg-sky-50 text-sky-700 ring-sky-600/20',
    agent: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    supervisor: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    admin: 'bg-violet-50 text-violet-700 ring-violet-600/20',
}[roleName] ?? 'bg-slate-100 text-slate-600 ring-slate-500/20');

const initials = (name) =>
    name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

const form = reactive({
    name: '',
    email: '',
    password: '',
    role_id: '',
    department_id: '',
    is_active: true,
});

function openCreate() {
    editing.value = null;
    formError.value = null;
    Object.assign(form, {
        name: '',
        email: '',
        password: '',
        role_id: '',
        department_id: '',
        is_active: true,
    });
    showModal.value = true;
}

function openEdit(user) {
    editing.value = user;
    formError.value = null;
    Object.assign(form, {
        name: user.name,
        email: user.email,
        password: '',
        role_id: user.role_id ?? '',
        department_id: user.department_id ?? '',
        is_active: Boolean(user.is_active),
    });
    showModal.value = true;
}

function handleSearch() {
    store.fetchUsers({ search: search.value.trim() }).catch(() => {});
}

/*
 * Guarda (crea o edita) al usuario. En edición se omite la contraseña si se
 * deja vacía, y el departamento se envía nulo para poder desasignarlo.
 */
async function handleSubmit() {
    saving.value = true;
    formError.value = null;

    const payload = {
        name: form.name,
        email: form.email,
        role_id: form.role_id,
        department_id: form.department_id || null,
        is_active: form.is_active,
    };

    if (form.password) {
        payload.password = form.password;
    }

    try {
        if (editing.value) {
            await store.updateUser(editing.value.id, payload);
        } else {
            await store.createUser(payload);
        }

        showModal.value = false;
        await store.fetchUsers({ search: search.value.trim() });
    } catch (err) {
        formError.value = store.error ?? 'No se pudo guardar el usuario.';
    } finally {
        saving.value = false;
    }
}

onMounted(() => {
    store.fetchUsers().catch(() => {});
    store.fetchCatalogs();
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Encabezado con buscador y acción de alta -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-bold text-teal-700 tracking-wider uppercase">Administración</p>
                <h2 class="text-xl font-black tracking-tight text-slate-900 mt-0.5">Gestión de Personal</h2>
                <p class="mt-1 text-sm text-slate-500">Alta, edición y estatus de las cuentas operativas del sistema.</p>
            </div>

            <button
                type="button"
                @click="openCreate"
                class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-gradient-to-r from-teal-600 to-cyan-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-teal-600/25 transition hover:from-teal-500 hover:to-cyan-400"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M10 3a.75.75 0 0 1 .75.75v5.5h5.5a.75.75 0 0 1 0 1.5h-5.5v5.5a.75.75 0 0 1-1.5 0v-5.5h-5.5a.75.75 0 0 1 0-1.5h5.5v-5.5A.75.75 0 0 1 10 3Z" />
                </svg>
                Nuevo usuario
            </button>
        </div>

        <!-- Buscador rápido -->
        <div class="relative max-w-md">
            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" />
            </svg>
            <input
                v-model="search"
                type="search"
                placeholder="Buscar por nombre o correo…"
                @keyup.enter="handleSearch"
                class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-4 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-teal-800 focus:ring-2 focus:ring-teal-800/20"
            />
        </div>

        <!-- Tabla de usuarios -->
        <div class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-sm">
            <div v-if="store.loading && store.users.length === 0" class="p-10 text-center text-sm text-slate-500">
                Cargando usuarios…
            </div>

            <div v-else-if="store.users.length === 0" class="p-10 text-center text-sm text-slate-500">
                No hay usuarios que coincidan con la búsqueda.
            </div>

            <table v-else class="w-full text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50/70 text-xs uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Usuario</th>
                        <th class="px-5 py-3 font-semibold">Rol</th>
                        <th class="px-5 py-3 font-semibold">Departamento</th>
                        <th class="px-5 py-3 font-semibold">Estatus</th>
                        <th class="px-5 py-3 text-right font-semibold">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="user in store.users" :key="user.id" class="transition hover:bg-slate-50/60">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tr from-teal-600 to-cyan-500 text-xs font-black text-white shadow-sm">
                                    {{ initials(user.name) }}
                                </span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-800">{{ user.name }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ user.email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset', roleBadge(user.role?.name)]">
                                {{ roleLabels[user.role?.name] ?? user.role?.name }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-slate-600">
                            {{ user.department?.name ?? '—' }}
                        </td>
                        <td class="px-5 py-3.5">
                            <span :class="user.is_active
                                ? 'inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/20'
                                : 'inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-500 ring-1 ring-inset ring-slate-500/20'">
                                <span class="h-1.5 w-1.5 rounded-full" :class="user.is_active ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                                {{ user.is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <button
                                type="button"
                                @click="openEdit(user)"
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
                                {{ editing ? 'Editar Usuario' : 'Nuevo Usuario' }}
                            </h3>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ editing ? `Actualizando la cuenta de ${editing.name}.` : 'Registra una cuenta operativa con su rol y departamento.' }}
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
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <label class="flex flex-col gap-1.5 text-sm font-medium text-slate-700">
                                Nombre completo
                                <input
                                    v-model.trim="form.name"
                                    type="text"
                                    required
                                    placeholder="Ej. María González"
                                    class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-teal-800 focus:ring-2 focus:ring-teal-800/20"
                                />
                            </label>

                            <label class="flex flex-col gap-1.5 text-sm font-medium text-slate-700">
                                Correo electrónico
                                <input
                                    v-model.trim="form.email"
                                    type="email"
                                    required
                                    placeholder="correo@empresa.com"
                                    class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-teal-800 focus:ring-2 focus:ring-teal-800/20"
                                />
                            </label>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <label class="flex flex-col gap-1.5 text-sm font-medium text-slate-700">
                                Rol
                                <select
                                    v-model="form.role_id"
                                    required
                                    class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none transition focus:border-teal-800 focus:ring-2 focus:ring-teal-800/20"
                                >
                                    <option value="" disabled>Selecciona un rol…</option>
                                    <option v-for="role in allowedRoles" :key="role.id" :value="role.id">
                                        {{ roleLabels[role.name] ?? role.name }}
                                    </option>
                                </select>
                            </label>

                            <label class="flex flex-col gap-1.5 text-sm font-medium text-slate-700">
                                Departamento
                                <select
                                    v-model="form.department_id"
                                    class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none transition focus:border-teal-800 focus:ring-2 focus:ring-teal-800/20"
                                >
                                    <option value="">Sin asignar</option>
                                    <option v-for="department in store.departments" :key="department.id" :value="department.id">
                                        {{ department.name }}
                                    </option>
                                </select>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <label class="flex flex-col gap-1.5 text-sm font-medium text-slate-700">
                                Contraseña
                                <input
                                    v-model="form.password"
                                    type="password"
                                    :required="!editing"
                                    :placeholder="editing ? 'Dejar vacía para no cambiarla' : 'Mínimo 8 caracteres'"
                                    minlength="8"
                                    class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-teal-800 focus:ring-2 focus:ring-teal-800/20"
                                />
                            </label>

                            <label class="flex items-center gap-3 self-end pb-2 text-sm font-medium text-slate-700">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-teal-700 focus:ring-teal-700"
                                />
                                Cuenta activa
                            </label>
                        </div>

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
                                {{ saving ? 'Guardando…' : editing ? 'Guardar cambios' : 'Crear usuario' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

import { extractError, useAuthStore } from '../stores/auth';

const auth = useAuthStore();

const roleLabels = {
    client: 'Cliente',
    agent: 'Agente',
    supervisor: 'Supervisor',
    admin: 'Administrador',
};

const roleBadges = {
    client: 'bg-sky-50 text-sky-700 ring-sky-600/20',
    agent: 'bg-teal-50 text-teal-700 ring-teal-600/20',
    supervisor: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
    admin: 'bg-rose-50 text-rose-700 ring-rose-600/20',
};

const profileForm = ref({
    name: '',
    email: '',
});

const passwordForm = ref({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const savingProfile = ref(false);
const savingPassword = ref(false);

const profileError = ref(null);
const profileSuccess = ref(null);
const passwordError = ref(null);
const passwordSuccess = ref(null);

const isClient = computed(() => auth.user?.role?.name === 'client');
const roleLabel = computed(() => roleLabels[auth.user?.role?.name] ?? auth.user?.role?.name);
const roleBadge = computed(() => roleBadges[auth.user?.role?.name] ?? 'bg-slate-100 text-slate-600 ring-slate-500/20');
const departmentName = computed(() => auth.user?.department?.name ?? null);
const memberSince = computed(() => formatDate(auth.user?.created_at));
const isActive = computed(() => Boolean(auth.user?.is_active));

function initials(name) {
    return name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

function formatDate(value) {
    if (!value) return '—';

    return new Date(value).toLocaleDateString('es-MX', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

async function saveProfile() {
    savingProfile.value = true;
    profileError.value = null;
    profileSuccess.value = null;

    try {
        await auth.updateProfile(profileForm.value);
        profileSuccess.value = 'Tus datos se guardaron correctamente.';
    } catch (err) {
        profileError.value = extractError(err);
    } finally {
        savingProfile.value = false;
    }
}

async function savePassword() {
    savingPassword.value = true;
    passwordError.value = null;
    passwordSuccess.value = null;

    try {
        await auth.updatePassword(passwordForm.value);
        passwordSuccess.value = 'Tu contraseña se actualizó correctamente.';
        passwordForm.value.current_password = '';
        passwordForm.value.password = '';
        passwordForm.value.password_confirmation = '';
    } catch (err) {
        passwordError.value = extractError(err);
    } finally {
        savingPassword.value = false;
    }
}

onMounted(() => {
    profileForm.value.name = auth.user?.name ?? '';
    profileForm.value.email = auth.user?.email ?? '';
});
</script>

<template>
    <section class="mx-auto w-full max-w-6xl">
        <!-- Tarjeta de identidad del usuario -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-teal-800 via-teal-700 to-cyan-700 p-8 text-white shadow-lg shadow-teal-800/20">
            <div class="absolute -right-20 -top-24 h-72 w-72 rounded-full bg-white/10 blur-2xl"></div>
            <div class="absolute -bottom-24 -left-16 h-64 w-64 rounded-full bg-cyan-300/10 blur-2xl"></div>

            <div class="relative flex flex-wrap items-center gap-6">
                <div class="flex h-24 w-24 shrink-0 items-center justify-center rounded-3xl bg-white/15 text-3xl font-black ring-4 ring-white/25 backdrop-blur">
                    {{ initials(auth.user?.name ?? '?') }}
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="truncate text-3xl font-black tracking-tight">{{ auth.user?.name ?? 'Usuario' }}</h2>
                        <span
                            v-if="!isActive"
                            class="rounded-full bg-red-500/15 px-3 py-1 text-xs font-bold uppercase tracking-wide text-red-200 ring-1 ring-inset ring-red-400/40"
                        >
                            Cuenta inactiva
                        </span>
                    </div>
                    <p class="mt-1 truncate text-teal-100">{{ auth.user?.email ?? '—' }}</p>
                    <p class="mt-3 text-sm text-teal-100/80">
                        Cliente desde el {{ memberSince }}
                        <span v-if="departmentName" class="hidden sm:inline"> · {{ departmentName }}</span>
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-2 rounded-2xl bg-white/10 px-4 py-2.5 text-sm font-semibold ring-1 ring-inset ring-white/20 backdrop-blur">
                    <span class="h-2 w-2 rounded-full bg-emerald-400 shadow shadow-emerald-400/50"></span>
                    Activo
                </div>
            </div>
        </div>

        <!-- Error/success global esporádico -->
        <div v-if="profileError || passwordError" class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium whitespace-pre-line text-red-700">
            {{ profileError || passwordError }}
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Columna principal: datos personales -->
            <div class="lg:col-span-2">
                <div class="rounded-3xl bg-white p-6 ring-1 ring-slate-200/70 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-teal-50 text-teal-600">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13.25a.75.75 0 0 0-1.5 0v4.94l3.03 1.75a.75.75 0 0 0 .75-1.3l-2.28-1.32V4.75Z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Datos personales</h3>
                            <p class="text-sm text-slate-500">Revisa tu información y corrígela si hace falta.</p>
                        </div>
                    </div>

                    <div v-if="profileSuccess" class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                        {{ profileSuccess }}
                    </div>

                    <form @submit.prevent="saveProfile" class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">
                                Nombre completo
                            </label>
                            <input
                                v-model="profileForm.name"
                                type="text"
                                required
                                maxlength="255"
                                autocomplete="name"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3 text-sm text-slate-800 transition focus:border-teal-500 focus:ring-2 focus:ring-teal-500/30 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">
                                Correo electrónico
                            </label>
                            <input
                                v-model="profileForm.email"
                                type="email"
                                required
                                autocomplete="email"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3 text-sm text-slate-800 transition focus:border-teal-500 focus:ring-2 focus:ring-teal-500/30 focus:outline-none"
                            />
                        </div>

                        <div class="sm:col-span-2">
                            <div class="flex items-start gap-2 rounded-2xl bg-amber-50/70 px-4 py-3 text-xs text-amber-800 ring-1 ring-inset ring-amber-600/10">
                                <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm2 5V5.5a2 2 0 1 0-4 0V6h4Z" clip-rule="evenodd" />
                                </svg>
                                <span>
                                    Por seguridad solo puedes modificar tu nombre y correo. El rol, departamento y estado de la cuenta los administra el equipo de soporte.
                                </span>
                            </div>
                        </div>

                        <div class="sm:col-span-2 flex justify-end">
                            <button
                                type="submit"
                                :disabled="savingProfile"
                                class="rounded-2xl bg-gradient-to-r from-teal-600 to-cyan-500 px-6 py-3 text-sm font-semibold text-white shadow-md shadow-teal-600/25 transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
                            >
                                {{ savingProfile ? 'Guardando…' : 'Guardar cambios' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Columna lateral: datos de cuenta -->
            <div class="space-y-6">
                <div class="rounded-3xl bg-white p-6 ring-1 ring-slate-200/70 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-sky-50 text-sky-600">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 2a6 6 0 0 0-6 6v3.586l-.707.707A1 1 0 0 0 4 14h12a1 1 0 0 0 .707-1.707L16 11.586V8a6 6 0 0 0-6-6Zm0 16a3 3 0 0 1-3-3h6a3 3 0 0 1-3 3Z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-slate-900">Información de la cuenta</h3>
                    </div>

                    <dl class="mt-5 space-y-4 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Rol</dt>
                            <dd class="font-semibold text-slate-800">{{ roleLabel }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Departamento</dt>
                            <dd class="truncate font-semibold text-slate-800">{{ departmentName ?? '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Estado</dt>
                            <dd class="inline-flex items-center gap-1.5 font-semibold text-emerald-700">
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                {{ isActive ? 'Activa' : 'Inactiva' }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500">Miembro desde</dt>
                            <dd class="text-right font-semibold text-slate-800">{{ memberSince }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Card de seguridad: cambio de contraseña -->
        <div class="mt-6 rounded-3xl bg-white p-6 ring-1 ring-slate-200/70 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm2 5V5.5a2 2 0 1 0-4 0V6h4Z" clip-rule="evenodd" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-slate-900">Cambiar contraseña</h3>
            </div>

            <div v-if="passwordSuccess" class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                {{ passwordSuccess }}
            </div>

            <form @submit.prevent="savePassword" class="mt-4">
                <div class="grid grid-cols-1 items-center gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">
                            Contraseña actual
                        </label>
                        <input
                            v-model="passwordForm.current_password"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm text-slate-800 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">
                            Nueva contraseña
                        </label>
                        <input
                            v-model="passwordForm.password"
                            type="password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm text-slate-800 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">
                            Confirmar nueva contraseña
                        </label>
                        <input
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-2.5 text-sm text-slate-800 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/30 focus:outline-none"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="savingPassword"
                        class="justify-self-end rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 px-7 py-3 text-sm font-semibold text-white shadow-md shadow-emerald-600/25 transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        {{ savingPassword ? 'Actualizando…' : 'Actualizar contraseña' }}
                    </button>
                </div>

                <p class="mt-2 text-xs text-slate-500">Usa al menos 8 caracteres.</p>
            </form>
        </div>
    </section>
</template>
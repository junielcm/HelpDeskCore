<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';

import NotificationBell from '../components/NotificationBell.vue';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const auth = useAuthStore();

const pageTitle = computed(() => {
    switch (route.name) {
        case 'tickets':
            return 'Bandeja de Tickets';
        case 'ticket-detail':
            return 'Detalle del Ticket';
        case 'profile':
            return 'Mi Perfil';
        case 'users':
            return 'Gestión de Personal';
        case 'departments':
            return 'Gestión de Departamentos';
        case 'reports':
            return 'Reportes';
        default:
            return 'Dashboard';
    }
});

const isManager = computed(() => ['supervisor', 'admin'].includes(auth.user?.role?.name));

const isClient = computed(() => auth.user?.role?.name === 'client');

const isAdmin = computed(() => auth.user?.role?.name === 'admin');

function isActive(section) {
    if (section === 'dashboard') {
        return route.name === 'dashboard';
    }

    if (section === 'profile') {
        return route.name === 'profile';
    }

    if (section === 'tickets') {
        return route.name === 'tickets' || route.name === 'ticket-detail';
    }

    if (section === 'users') {
        return route.name === 'users';
    }

    if (section === 'departments') {
        return route.name === 'departments';
    }

    if (section === 'reports') {
        return route.name === 'reports';
    }

    return false;
}

function initials(name) {
    return name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}
</script>

<template>
    <!-- Contenedor general con gradiente ambiental profundo y sombras flotantes de alta calidad -->
    <div
        class="flex h-screen w-full overflow-hidden rounded-[2.5rem] border-[10px] border-white/70 bg-gradient-to-br from-[#d0f4ef] via-[#dcedfc] to-[#e8e2fc] shadow-[0_30px_70px_-15px_rgba(0,0,0,0.18)]"
    >
        <!-- Sidebar lateral flotante con efecto cristal esmerilado avanzado -->
        <aside class="m-4 flex w-76 shrink-0 flex-col rounded-[2.2rem] bg-white/45 p-6 backdrop-blur-3xl border border-white/60 text-slate-700 shadow-[0_20px_40px_rgba(0,0,0,0.04)]">
            
            <!-- Marca con insignia brillante y efectos de luz -->
            <div class="flex items-center gap-3.5 px-2 mb-10">
                <div class="relative flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-tr from-teal-600 to-cyan-400 text-white shadow-lg shadow-teal-500/30 ring-4 ring-white/60 font-black text-lg">
                    H
                    <div class="absolute -top-1 -right-1 h-3.5 w-3.5 rounded-full bg-emerald-400 border-2 border-white animate-pulse"></div>
                </div>
                <div>
                    <p class="text-base font-black tracking-tight text-slate-900 bg-gradient-to-r from-slate-900 to-teal-800 bg-clip-text text-transparent">HelpDesk</p>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-teal-700/70">Centro de Soporte</p>
                </div>
            </div>

            <!-- Navegación lateral con diseño tipo pastilla flotante y efectos hover -->
            <nav class="flex flex-1 flex-col gap-2.5">
                <p class="px-4 text-[10px] font-extrabold tracking-wider uppercase text-slate-400 mb-1">Menú Principal</p>
                
                <router-link
                    v-if="isManager"
                    to="/"
                    :class="isActive('dashboard')
                        ? 'bg-gradient-to-r from-teal-600 via-teal-500 to-cyan-500 text-white font-bold shadow-lg shadow-teal-600/30 scale-[1.02]'
                        : 'text-slate-600 hover:bg-white/60 hover:text-slate-900 hover:translate-x-1'"
                    class="group relative flex items-center gap-3.5 rounded-2xl px-4 py-3.5 text-sm transition-all duration-300 ease-out"
                >
                    <div :class="isActive('dashboard') ? 'bg-white/20 text-white' : 'bg-white/50 text-teal-600 group-hover:bg-white group-hover:shadow-sm'" class="flex h-9 w-9 items-center justify-center rounded-xl transition-all">
                        <svg class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M10 2.5 2.5 9h2V17H8.5v-4h3v4h4V9h2L10 2.5Z" />
                        </svg>
                    </div>
                    <span>Dashboard</span>
                </router-link>

                <router-link
                    to="/perfil"
                    :class="isActive('profile')
                        ? 'bg-gradient-to-r from-teal-600 via-teal-500 to-cyan-500 text-white font-bold shadow-lg shadow-teal-600/30 scale-[1.02]'
                        : 'text-slate-600 hover:bg-white/60 hover:text-slate-900 hover:translate-x-1'"
                    class="group relative flex items-center gap-3.5 rounded-2xl px-4 py-3.5 text-sm transition-all duration-300 ease-out"
                >
                    <div :class="isActive('profile') ? 'bg-white/20 text-white' : 'bg-white/50 text-teal-600 group-hover:bg-white group-hover:shadow-sm'" class="flex h-9 w-9 items-center justify-center rounded-xl transition-all">
                        <svg class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 9a3.75 3.75 0 1 0 0-7.5A3.75 3.75 0 0 0 10 9Zm0 1.5a9.33 9.33 0 0 0-5.23 1.62 1.75 1.75 0 0 0-.46 2.55l.65.9c.2.28.53.43.88.43H14.2c.36 0 .68-.15.89-.43l.65-.9a1.75 1.75 0 0 0-.46-2.55A9.33 9.33 0 0 0 10 10.5Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <span>Mi Perfil</span>
                </router-link>

                <router-link
                    to="/tickets"
                    :class="isActive('tickets')
                        ? 'bg-gradient-to-r from-teal-600 via-teal-500 to-cyan-500 text-white font-bold shadow-lg shadow-teal-600/30 scale-[1.02]'
                        : 'text-slate-600 hover:bg-white/60 hover:text-slate-900 hover:translate-x-1'"
                    class="group relative flex items-center gap-3.5 rounded-2xl px-4 py-3.5 text-sm transition-all duration-300 ease-out"
                >
                    <div :class="isActive('tickets') ? 'bg-white/20 text-white' : 'bg-white/50 text-teal-600 group-hover:bg-white group-hover:shadow-sm'" class="flex h-9 w-9 items-center justify-center rounded-xl transition-all">
                        <svg class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M4 3.5h12a.5.5 0 0 1 .5.5v.75a.75.75 0 0 1-.22.53L13.4 8.1V15a.5.5 0 0 1-.3.46l-3 1.5a.5.5 0 0 1-.7-.46V8.1L3.72 5.28A.75.75 0 0 1 3.5 4.75V4a.5.5 0 0 1 .5-.5Z" />
                        </svg>
                    </div>
                    <span>Bandeja de Tickets</span>
                </router-link>

                <router-link
                    v-if="isManager"
                    to="/usuarios"
                    :class="isActive('users')
                        ? 'bg-gradient-to-r from-teal-600 via-teal-500 to-cyan-500 text-white font-bold shadow-lg shadow-teal-600/30 scale-[1.02]'
                        : 'text-slate-600 hover:bg-white/60 hover:text-slate-900 hover:translate-x-1'"
                    class="group relative flex items-center gap-3.5 rounded-2xl px-4 py-3.5 text-sm transition-all duration-300 ease-out"
                >
                    <div :class="isActive('users') ? 'bg-white/20 text-white' : 'bg-white/50 text-teal-600 group-hover:bg-white group-hover:shadow-sm'" class="flex h-9 w-9 items-center justify-center rounded-xl transition-all">
                        <svg class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm-7 5.5c0 1.38 1.57 2.5 3.5 2.5h7c1.93 0 3.5-1.12 3.5-2.5 0-2.21-2.69-4-7-4s-7 1.79-7 4Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <span>Gestión de Personal</span>
                </router-link>

                <router-link
                    v-if="isAdmin"
                    to="/departamentos"
                    :class="isActive('departments')
                        ? 'bg-gradient-to-r from-teal-600 via-teal-500 to-cyan-500 text-white font-bold shadow-lg shadow-teal-600/30 scale-[1.02]'
                        : 'text-slate-600 hover:bg-white/60 hover:text-slate-900 hover:translate-x-1'"
                    class="group relative flex items-center gap-3.5 rounded-2xl px-4 py-3.5 text-sm transition-all duration-300 ease-out"
                >
                    <div :class="isActive('departments') ? 'bg-white/20 text-white' : 'bg-white/50 text-teal-600 group-hover:bg-white group-hover:shadow-sm'" class="flex h-9 w-9 items-center justify-center rounded-xl transition-all">
                        <svg class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M2 4.5A2.5 2.5 0 0 1 4.5 2h2A2.5 2.5 0 0 1 9 4.5v2a2.5 2.5 0 0 1-1.5 2.307V9H9a.75.75 0 0 1 0 1.5h-4A.75.75 0 0 1 5 9.75v-.443A2.5 2.5 0 0 1 3.5 6.5v-2Zm6 7A2.5 2.5 0 0 1 10.5 9h2a2.5 2.5 0 0 1 2.5 2.5v2a2.5 2.5 0 0 1-2.5 2.5h-2A2.5 2.5 0 0 1 8 13.5v-2Zm2-4a1 1 0 0 0 0 2h2a1 1 0 0 0 0-2h-2Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <span>Gestión de Departamentos</span>
                </router-link>

                <router-link
                    v-if="isManager"
                    to="/reportes"
                    :class="isActive('reports')
                        ? 'bg-gradient-to-r from-teal-600 via-teal-500 to-cyan-500 text-white font-bold shadow-lg shadow-teal-600/30 scale-[1.02]'
                        : 'text-slate-600 hover:bg-white/60 hover:text-slate-900 hover:translate-x-1'"
                    class="group relative flex items-center gap-3.5 rounded-2xl px-4 py-3.5 text-sm transition-all duration-300 ease-out"
                >
                    <div :class="isActive('reports') ? 'bg-white/20 text-white' : 'bg-white/50 text-teal-600 group-hover:bg-white group-hover:shadow-sm'" class="flex h-9 w-9 items-center justify-center rounded-xl transition-all">
                        <svg class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M3 2.5A1.5 1.5 0 0 1 4.5 1h7.344a1.5 1.5 0 0 1 1.06.44l2.656 2.656A1.5 1.5 0 0 1 16 5.156V17.5a1.5 1.5 0 0 1-1.5 1.5h-10A1.5 1.5 0 0 1 3 17.5v-15Zm3.25 4a.75.75 0 0 0 0 1.5h7.5a.75.75 0 0 0 0-1.5h-7.5Zm0 3.25a.75.75 0 0 0 0 1.5h7.5a.75.75 0 0 0 0-1.5h-7.5Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <span>Reportes</span>
                </router-link>
            </nav>

            <!-- Sección inferior con línea de separación difuminada y botón de cerrar sesión estilizado -->
            <div class="pt-4 mt-auto border-t border-white/60">
                <button
                    type="button"
                    @click="auth.logout()"
                    class="group flex w-full items-center gap-3.5 rounded-2xl px-4 py-3.5 text-sm font-semibold text-rose-600 bg-rose-500/5 hover:bg-rose-500/15 border border-rose-500/10 transition-all duration-200"
                >
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-500/10 text-rose-600 group-hover:scale-110 transition-transform">
                        <svg class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M3 4.25A2.25 2.25 0 0 1 5.25 2h5.5A2.25 2.25 0 0 1 13 4.25v2.5a.75.75 0 0 1-1.5 0v-2.5a.75.75 0 0 0-.75-.75h-5.5a.75.75 0 0 0-.75.75v11.5c0 .414.336.75.75.75h5.5a.75.75 0 0 0 .75-.75v-2.5a.75.75 0 0 1 1.5 0v2.5A2.25 2.25 0 0 1 10.75 18h-5.5A2.25 2.25 0 0 1 3 15.75V4.25Z" clip-rule="evenodd" />
                            <path fill-rule="evenodd" d="M19 10a.75.75 0 0 0-.75-.75H8.69l2.22-2.22a.75.75 0 0 0-1.06-1.06l-3.5 3.5a.75.75 0 0 0 0 1.06l3.5 3.5a.75.75 0 1 0 1.06-1.06l-2.22-2.22h9.56c.414 0 .75-.336.75-.75Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <span>Cerrar sesión</span>
                </button>
            </div>
        </aside>

        <!-- Lienzo principal con diseño flotante moderno -->
        <div class="flex min-w-0 flex-1 flex-col bg-transparent my-4 mr-4">
            <!-- Header superior elegante -->
            <header class="flex items-center justify-between gap-4 px-6 py-4">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold text-teal-700 tracking-wider uppercase">Panel de Control</p>
                    <h1 class="text-2xl font-black tracking-tight text-slate-900 mt-0.5">{{ pageTitle }}</h1>
                </div>

                <!-- Campana de notificaciones y tarjeta de usuario -->
                <div class="flex shrink-0 items-center gap-3">
                    <NotificationBell />

                    <div class="flex items-center gap-3.5 bg-white/50 backdrop-blur-xl px-4 py-2 rounded-2xl border border-white/70 shadow-sm">
                        <span class="text-xs font-bold text-slate-800">
                            {{ auth.user?.name ?? 'Usuario' }}
                        </span>

                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-tr from-teal-600 to-cyan-500 text-xs font-black text-white shadow-md shadow-teal-500/25">
                            {{ initials(auth.user?.name ?? '?') }}
                        </span>
                    </div>
                </div>
            </header>

            <!-- Contenedor principal de vistas -->
            <main class="flex-1 overflow-y-auto px-6 pb-6">
                <div class="h-[calc(100vh-4rem)] rounded-[2rem] bg-white/50 backdrop-blur-2xl border border-white/75 p-6 shadow-[0_20px_50px_rgba(0,0,0,0.06)]">
                    <router-view />
                </div>
            </main>
        </div>
    </div>
</template>
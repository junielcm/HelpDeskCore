<script setup>
import { computed, onMounted } from 'vue';

import { useAuthStore } from '../stores/auth';
import AdminDashboard from './Dashboard/AdminDashboard.vue';

const auth = useAuthStore();

const isManager = computed(() => {
    return ['supervisor', 'admin'].includes(auth.user?.role?.name);
});

onMounted(() => {
    auth.fetchUserProfile().catch(() => {});
});
</script>

<template>
    <AdminDashboard v-if="isManager" />
    <section v-else>
        <h2 class="text-2xl font-semibold">Dashboard</h2>
        <p class="mt-2 text-sm text-[#706f6c] dark:text-[#A1A09A]">
            Bienvenido, {{ auth.user?.name ?? 'usuario' }}.
        </p>
    </section>
</template>

import { ref } from 'vue';
import { defineStore } from 'pinia';

import http from '../services/axios';

/*
 * Store del dashboard ejecutivo.
 *
 * Consume el endpoint consolidado de métricas (GET /api/dashboard/metrics),
 * protegido por Sanctum, que devuelve de una sola vez los KPIs, la
 * distribución por estado, la carga por departamento y la tendencia diaria.
 */
export const useDashboardStore = defineStore('dashboard', () => {
    const loading = ref(false);
    const error = ref(null);
    const metrics = ref(null);

    async function fetchMetrics() {
        loading.value = true;
        error.value = null;

        try {
            const { data } = await http.get('/dashboard/metrics');

            metrics.value = data.metrics;
        } catch (err) {
            error.value = err.response?.data?.message ?? 'No se pudieron cargar las métricas.';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    function clear() {
        metrics.value = null;
        error.value = null;
    }

    return {
        loading,
        error,
        metrics,
        fetchMetrics,
        clear,
    };
});

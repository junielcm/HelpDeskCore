import { ref } from 'vue';
import { defineStore } from 'pinia';

import http from '../services/axios';

/*
 * Store de SLA (Acuerdos de Nivel de Servicio).
 *
 * Gestiona la configuración de los umbrales por prioridad y el estado general
 * de cumplimiento. Ambos datos son de lectura/gestión para supervisor/admin,
 * protegidos por Sanctum en el backend.
 */
export const useSlaStore = defineStore('sla', () => {
    const loading = ref(false);
    const error = ref(null);
    const rules = ref([]);
    const status = ref(null);

    /*
     * Carga las reglas de SLA (umbrales por prioridad) y el resumen de
     * cumplimiento en paralelo, para poblar la sección del dashboard.
     */
    async function fetchAll() {
        loading.value = true;
        error.value = null;

        try {
            const [rulesRes, statusRes] = await Promise.all([
                http.get('/sla/rules'),
                http.get('/sla/status'),
            ]);

            rules.value = rulesRes.data.rules;
            status.value = statusRes.data.sla;
        } catch (err) {
            error.value = err.response?.data?.message ?? 'No se pudieron cargar los acuerdos de SLA.';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /*
     * Actualiza los umbrales (respuesta/resolución) de una regla concreta.
     */
    async function updateRule(rule) {
        error.value = null;

        try {
            const { data } = await http.put(`/sla/rules/${rule.id}`, {
                response_hours: rule.response_hours,
                resolution_hours: rule.resolution_hours,
            });

            const index = rules.value.findIndex((item) => item.id === rule.id);

            if (index !== -1) {
                rules.value[index] = data.rule;
            }

            return data.rule;
        } catch (err) {
            error.value = err.response?.data?.message ?? 'No se pudo actualizar la regla SLA.';
            throw err;
        }
    }

    return {
        loading,
        error,
        rules,
        status,
        fetchAll,
        updateRule,
    };
});

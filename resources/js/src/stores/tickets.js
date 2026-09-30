import { computed, ref } from 'vue';
import { defineStore } from 'pinia';

import http from '../services/axios';

/*
 * Store global de la bandeja de tickets.
 *
 * Centraliza el listado, los filtros rápidos de estado/prioridad y los
 * contadores que muestran el tamaño de cada cola. Al vivir en Pinia, cualquier
 * vista consume el mismo estado sin duplicar peticiones ni lógica de filtrado.
 */
export const useTicketsStore = defineStore('tickets', () => {
    const tickets = ref([]);
    const loading = ref(false);
    const error = ref(null);

    const activeStatus = ref('all');
    const activePriority = ref('all');

    /*
     * Filtro en cliente sobre el listado devuelto por el API. El backend ya
     * acota por rol (client ve sus tickets, agent los de su departamento);
     * aquí sólo cambiamos la cola visible.
     */
    const filteredTickets = computed(() => {
        return tickets.value.filter((ticket) => {
            const matchesStatus = activeStatus.value === 'all' || ticket.status === activeStatus.value;
            const matchesPriority = activePriority.value === 'all' || ticket.priority === activePriority.value;

            return matchesStatus && matchesPriority;
        });
    });

    const countByStatus = computed(() => {
        return (key) => (key === 'all' ? tickets.value.length : tickets.value.filter((t) => t.status === key).length);
    });

    const countByPriority = computed(() => {
        return (key) => (key === 'all' ? tickets.value.length : tickets.value.filter((t) => t.priority === key).length);
    });

    async function fetchTickets() {
        loading.value = true;
        error.value = null;

        try {
            // 'paginate' mantiene el listado ligero; el API responde el paginador
            // bajo data.tickets.data cuando está paginado.
            const { data } = await http.get('/tickets', { params: { paginate: true } });

            tickets.value = data.tickets?.data ?? data.tickets;
        } catch (err) {
            error.value = err.response?.data?.message ?? 'No se pudieron cargar tus tickets.';
        } finally {
            loading.value = false;
        }
    }

    function setStatus(key) {
        activeStatus.value = key;
    }

    function setPriority(key) {
        activePriority.value = key;
    }

    return {
        tickets,
        loading,
        error,
        activeStatus,
        activePriority,
        filteredTickets,
        countByStatus,
        countByPriority,
        fetchTickets,
        setStatus,
        setPriority,
    };
});
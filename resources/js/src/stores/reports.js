import { ref } from 'vue';
import { defineStore } from 'pinia';

import http from '../services/axios';

/*
 * Store de reportes del panel de administración.
 *
 * Gestiona la descarga de hojas de cálculo (.xlsx). La acción solicita el
 * archivo como blob (responseType: 'blob') y dispara la descarga dinámica
 * en el navegador del usuario.
 */
export const useReportsStore = defineStore('reports', () => {
    const loading = ref(false);
    const error = ref(null);

    /*
     * Descarga el reporte de tickets en .xlsx aplicando los filtros opcionales
     * (estatus, prioridad, departamento y rango de fechas).
     */
    async function downloadTicketsExcel(params = {}) {
        loading.value = true;
        error.value = null;

        try {
            const response = await http.get('/reports/tickets/excel', {
                params,
                responseType: 'blob',
            });

            triggerDownload(response.data, 'reporte-tickets.xlsx');
        } catch (err) {
            error.value = 'No se pudo descargar el reporte.';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    return {
        loading,
        error,
        downloadTicketsExcel,
    };
});

/*
 * Fuerza la descarga de un blob en el navegador creando un enlace temporal.
 */
function triggerDownload(blob, filename) {
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
}

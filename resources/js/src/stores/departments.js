import { ref } from 'vue';
import { defineStore } from 'pinia';

import http from '../services/axios';

/*
 * Store de gestión de departamentos del panel de administración.
 *
 * Trabaja contra los endpoints de administración (lista completa en
 * /admin/departments y alta/edición en /departments), que exigen el rol
 * administrador. A diferencia del catálogo público, aquí se listan también
 * los departamentos inactivos.
 */
export const useDepartmentsStore = defineStore('departments', () => {
    const departments = ref([]);
    const loading = ref(false);
    const error = ref(null);

    function extractError(err) {
        const fields = err?.response?.data?.errors;

        if (fields) {
            return Object.entries(fields)
                .map(([field, messages]) => {
                    const label = {
                        name: 'nombre',
                        description: 'descripción',
                        is_active: 'estatus',
                    }[field] ?? field;

                    return `${label}: ${messages[0]}`;
                })
                .join('\n');
        }

        return err?.response?.data?.message ?? 'No se pudo completar la operación.';
    }

    async function fetchDepartments() {
        loading.value = true;
        error.value = null;

        try {
            const { data } = await http.get('/admin/departments');

            departments.value = data.departments ?? [];
        } catch (err) {
            error.value = extractError(err);
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function createDepartment(payload) {
        loading.value = true;
        error.value = null;

        try {
            const { data } = await http.post('/departments', payload);

            departments.value.push(data.department);
        } catch (err) {
            error.value = extractError(err);
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function updateDepartment(departmentId, payload) {
        loading.value = true;
        error.value = null;

        try {
            const { data } = await http.put(`/departments/${departmentId}`, payload);

            const index = departments.value.findIndex((department) => department.id === departmentId);

            if (index !== -1) {
                departments.value[index] = data.department;
            }
        } catch (err) {
            error.value = extractError(err);
            throw err;
        } finally {
            loading.value = false;
        }
    }

    return {
        departments,
        loading,
        error,
        fetchDepartments,
        createDepartment,
        updateDepartment,
    };
});

import { computed, ref } from 'vue';
import { defineStore } from 'pinia';

import http from '../services/axios';

/*
 * Store de gestión de usuarios del panel administrativo.
 *
 * Centraliza el listado, la búsqueda y el alta/edición de cuentas
 * operativas. Expone los catálogos de roles y departamentos que necesita
 * el formulario del modal. Todos los endpoints exigen rol supervisor/admin.
 */
export const useUsersStore = defineStore('users', () => {
    const users = ref([]);
    const roles = ref([]);
    const departments = ref([]);
    const loading = ref(false);
    const error = ref(null);

    const roleMap = computed(() => Object.fromEntries(roles.value.map((role) => [role.id, role])));

    /*
     * Traduce un 422 del backend a un mensaje legible enumerando los campos.
     */
    function extractError(err) {
        const fields = err?.response?.data?.errors;

        if (fields) {
            return Object.entries(fields)
                .map(([field, messages]) => {
                    const label = {
                        name: 'nombre',
                        email: 'correo',
                        password: 'contraseña',
                        role_id: 'rol',
                        department_id: 'departamento',
                    }[field] ?? field;

                    return `${label}: ${messages[0]}`;
                })
                .join('\n');
        }

        return err?.response?.data?.message ?? 'No se pudo completar la operación.';
    }

    async function fetchUsers(params = {}) {
        loading.value = true;
        error.value = null;

        try {
            const { data } = await http.get('/users', { params });

            users.value = data.users ?? [];
        } catch (err) {
            error.value = extractError(err);
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function createUser(payload) {
        loading.value = true;
        error.value = null;

        try {
            await http.post('/users', payload);
        } catch (err) {
            error.value = extractError(err);
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function updateUser(userId, payload) {
        loading.value = true;
        error.value = null;

        try {
            await http.put(`/users/${userId}`, payload);
        } catch (err) {
            error.value = extractError(err);
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function fetchCatalogs() {
        try {
            const [{ data: roleData }, { data: deptData }] = await Promise.all([
                http.get('/roles'),
                http.get('/departments'),
            ]);

            roles.value = roleData.roles ?? [];
            departments.value = deptData.departments ?? [];
        } catch {
            roles.value = [];
            departments.value = [];
        }
    }

    return {
        users,
        roles,
        departments,
        loading,
        error,
        roleMap,
        fetchUsers,
        createUser,
        updateUser,
        fetchCatalogs,
    };
});

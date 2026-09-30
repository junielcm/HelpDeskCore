import { computed, ref } from 'vue';
import { defineStore } from 'pinia';

import http from '../services/axios';

const TOKEN_KEY = 'auth_token';
const USER_KEY = 'auth_user';

/*
 * Etiquetas de campo para traducir los errores de validación del backend
 * (la validación del API responde en inglés) a un mensaje legible.
 */
const validationLabels = {
    name: 'nombre',
    email: 'correo',
    password: 'contraseña',
    current_password: 'contraseña actual',
    password_confirmation: 'confirmación de contraseña',
    title: 'asunto',
    description: 'descripción',
    department_id: 'departamento',
    priority: 'prioridad',
    body: 'mensaje',
    attachment: 'archivo adjunto',
};

function readStoredUser() {
    const stored = localStorage.getItem(USER_KEY);

    if (!stored) {
        return null;
    }

    try {
        return JSON.parse(stored);
    } catch {
        // Un valor corrupto en storage no debe impedir el arranque del SPA.
        localStorage.removeItem(USER_KEY);
        return null;
    }
}

/*
 * Traduce cualquier error de Axios a un mensaje amigable para la interfaz:
 * - Errores 422 → enumera los mensajes de campo, con la llave traducida.
 * - Errores 401/403/500 → usa el campo message que devuelve la API.
 * - Sin respuesta (red/CORS/proxy) → mensaje de conexión.
 */
export function extractError(error) {
    if (!error?.response) {
        return 'No se pudo conectar con el servidor. Verifica que esté en ejecución.';
    }

    if (error.response.status === 422) {
        const fields = error.response.data?.errors;

        if (fields) {
            return Object.entries(fields)
                .map(([field, messages]) => {
                    const label = validationLabels[field] ?? field;

                    return `${label}: ${messages[0]}`;
                })
                .join('\n');
        }
    }

    return error.response.data?.message ?? 'No se pudo completar la acción.';
}

const token = ref(localStorage.getItem(TOKEN_KEY));
const user = ref(readStoredUser());

/*
 * Persiste la sesión devuelta por login/register en el estado local.
 */
function applySession(data) {
    token.value = data.token;
    user.value = data.user;
    localStorage.setItem(TOKEN_KEY, data.token);
    localStorage.setItem(USER_KEY, JSON.stringify(data.user));
}

export const useAuthStore = defineStore('auth', () => {
    const loading = ref(false);
    const error = ref(null);

    const isAuthenticated = computed(() => Boolean(token.value));

    function clearSession() {
        token.value = null;
        user.value = null;
        localStorage.removeItem(TOKEN_KEY);
        localStorage.removeItem(USER_KEY);
    }

    /*
     * Inicia sesión contra POST /api/login.
     *
     * Si el servidor responde 401 (credenciales inválidas) o 422 (validación
     * de campos), se expone el mensaje en `error` y se relanza el error para
     * que la vista pueda reaccionar.
     */
    async function login(credentials) {
        loading.value = true;
        error.value = null;

        try {
            const { data } = await http.post('/login', credentials);

            if (!data?.token || !data?.user) {
                error.value = 'El servidor no devolvió una sesión válida.';
                throw new Error('invalid_response');
            }

            applySession(data);
        } catch (err) {
            error.value = extractError(err);
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /*
     * Crea una cuenta de cliente contra POST /api/register y la deja con
     * sesión iniciada (el backend fuerza siempre el rol client).
     */
    async function register(payload) {
        loading.value = true;
        error.value = null;

        try {
            const { data } = await http.post('/register', payload);

            if (!data?.token || !data?.user) {
                error.value = 'El servidor no devolvió una sesión válida.';
                throw new Error('invalid_response');
            }

            applySession(data);
        } catch (err) {
            error.value = extractError(err);
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /*
     * Restaura el perfil desde el API al recargar la página (GET /api/profile)
     * para validar que el token guardado sigue siendo válido.
     */
    async function fetchUserProfile() {
        loading.value = true;
        error.value = null;

        try {
            const { data } = await http.get('/profile');

            if (!data?.user) {
                clearSession();
                throw new Error('invalid_profile');
            }

            user.value = data.user;
            localStorage.setItem(USER_KEY, JSON.stringify(data.user));
        } catch (err) {
            error.value = extractError(err);
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /*
     * Actualiza el perfil del usuario autenticado (PUT /api/profile) y
     * refresca el estado local para que la UI refleje los cambios al instante.
     */
    async function updateProfile(payload) {
        const { data } = await http.put('/profile', payload);

        if (!data?.user) {
            throw new Error('invalid_profile');
        }

        user.value = data.user;
        localStorage.setItem(USER_KEY, JSON.stringify(data.user));

        return data.user;
    }

    /*
     * Cambia la contraseña del usuario autenticado (PUT /api/profile/password).
     */
    async function updatePassword(payload) {
        const { data } = await http.put('/profile/password', payload);

        return data;
    }

    /*
     * Cierra la sesión invalidando el token en el API y limpiando el storage
     * local. Aunque la petición falle, la sesión local siempre se limpia.
     */
    async function logout() {
        try {
            await http.post('/logout');
        } catch {
            // se cierra la sesión local aunque la petición falle
        } finally {
            clearSession();
        }
    }

    return {
        token,
        user,
        loading,
        error,
        isAuthenticated,
        login,
        register,
        logout,
        fetchUserProfile,
        updateProfile,
        updatePassword,
        clearSession,
    };
});
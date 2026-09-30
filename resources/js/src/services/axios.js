import axios from 'axios';

const http = axios.create({
    baseURL: `${window.location.origin}/api`,
    headers: {
        Accept: 'application/json',
    },
});

http.interceptors.request.use((config) => {
    const token = localStorage.getItem('auth_token');

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
});

http.interceptors.response.use(
    (response) => response,
    async (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');

            const { router } = await import('../router');

            if (router.currentRoute.value.name !== 'login') {
                await router.push({ name: 'login' });
            }
        }

        return Promise.reject(error);
    },
);

export default http;
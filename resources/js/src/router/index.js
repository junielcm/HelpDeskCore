import { createRouter, createWebHistory } from 'vue-router';

import { useAuthStore } from '../stores/auth';
import AppLayout from '../layouts/AppLayout.vue';
import LoginView from '../views/auth/LoginView.vue';
import DashboardView from '../views/DashboardView.vue';
import TicketListView from '../views/tickets/TicketListView.vue';
import TicketDetailView from '../views/tickets/TicketDetailView.vue';
import ProfileView from '../views/ProfileView.vue';
import UserManagementView from '../views/Admin/UserManagement.vue';
import DepartmentManagementView from '../views/Admin/DepartmentManagement.vue';
import ReportManagementView from '../views/Reports/ReportManagement.vue';

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/login',
            name: 'login',
            component: LoginView,
            meta: { guest: true },
        },
        {
            path: '/',
            component: AppLayout,
            meta: { requiresAuth: true },
            children: [
                {
                    path: '',
                    name: 'dashboard',
                    component: DashboardView,
                },
                {
                    path: 'perfil',
                    name: 'profile',
                    component: ProfileView,
                },
                {
                    path: 'tickets',
                    name: 'tickets',
                    component: TicketListView,
                },
                {
                    path: 'tickets/:id',
                    name: 'ticket-detail',
                    component: TicketDetailView,
                },
                {
                    path: 'usuarios',
                    name: 'users',
                    component: UserManagementView,
                    meta: { roles: ['supervisor', 'admin'] },
                },
                {
                    path: 'departamentos',
                    name: 'departments',
                    component: DepartmentManagementView,
                    meta: { roles: ['admin'] },
                },
                {
                    path: 'reportes',
                    name: 'reports',
                    component: ReportManagementView,
                    meta: { roles: ['supervisor', 'admin'] },
                },
            ],
        },
        {
            path: '/:pathMatch(.*)*',
            redirect: '/',
        },
    ],
});

router.beforeEach((to) => {
    const auth = useAuthStore();

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login' };
    }

    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }

    if (to.meta.roles && !to.meta.roles.includes(auth.user?.role?.name)) {
        return { name: 'dashboard' };
    }

    // Cliente y agente no tienen dashboard: aterrizan en su espacio de trabajo.
    if (to.name === 'dashboard') {
        const role = auth.user?.role?.name;

        if (role === 'client') {
            return { name: 'profile' };
        }

        if (role === 'agent') {
            return { name: 'tickets' };
        }
    }
});
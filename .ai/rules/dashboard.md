---
paths:
  - 'resources/js/src/views/Dashboard/**'
---

# Dashboard

## Panel de métricas solo para supervisor/admin
DashboardView.vue renderiza AdminDashboard.vue (KPIs + gráficos Tailwind nativos + AuditLogModal) solo si auth.user.role.name es supervisor/admin. Endpoints /dashboard/* exigen rol de gestión. La bitácora se filtra por usuario/acción/rango de fechas vía /dashboard/audit-logs.

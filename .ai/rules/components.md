---
paths:
  - resources/js/src/components/NotificationBell.vue
---

# Components

## Campana de notificaciones del layout
La campana usa el store Pinia stores/notifications.js contra /api/notifications. data del DB notification es JSON string y el type es el FQCN; el componente hace split('\\').pop() y parsea data para armar el mensaje. Al hacer clic marca leída y navega al ticket.

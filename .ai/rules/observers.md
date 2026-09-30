---
paths:
  - 'app/Observers/**'
---

# Observers

## Emisión de notificaciones vía TicketObserver
Las notificaciones se emiten desde TicketObserver (no desde controladores) usando el channel 'database'. TicketCreated -> agentes+supervisores del departamento; TicketAssigned -> agente recién asignado; TicketStatusChanged -> cliente. Nota para tests: crear un ticket en el departamento de un usuario genera automáticamente la notificación TicketCreated, por lo que los tests de controlador de notificaciones usan tickets en departamentos sin personal.

---
paths:
  - 'resources/js/src/views/Admin/**'
---

# Admin

## Vista de gestión de usuarios y guarda por rol
La gestión de personal vive en views/Admin/UserManagement.vue + store stores/users.js. La ruta /usuarios exige meta.roles ['supervisor','admin'] y el router ya redirige al dashboard si el rol no coincide. El sidebar (AppLayout) muestra el enlace solo a managers via isManager.

## Rutas y visibilidad del panel admin
El panel de administración usa rutas del layout /usuarios (roles supervisor+admin) y /departamentos (SOLO admin). Cada una tiene su store Pinia (stores/users.js, stores/departments.js). La ruta /departamentos es más restrictiva que /usuarios: el enlace del sidebar se muestra con isAdmin mientras usuarios usa isManager.

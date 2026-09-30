# HelpDeskCore

Sistema de gestión de tickets y mesa de ayuda (*help desk*). Backend en **Laravel 13** con API
JSON (Sanctum) y frontend **SPA en Vue 3** + Pinia + Tailwind 4, compilado con Vite.

Incluye tickets con adjuntos y auditoría, comentarios internos, departments, reglas y seguimiento
de **SLA**, notificaciones, calificación de satisfacción, panel de métricas y exportación a Excel.

Todo el proyecto se levanta con Docker: no hace falta tener PHP, Node ni Composer instalados.

---

## Arranque rápido (recomendado)

```bash
git clone https://github.com/junielcm/HelpDeskCore.git
cd HelpDeskCore
docker compose up -d --build
```

Abre <http://localhost:8000>.

El contenedor se encarga solo de generar `.env`, la `APP_KEY`, crear la base de datos SQLite y
ejecutar las migraciones. No hay ningún paso previo.

---

## Cuentas de prueba

Para cargar los usuarios de demostración de los cuatro roles:

```bash
docker compose exec app php artisan db:seed
```

**Todas las cuentas usan la contraseña `123456`** (definida en
`database/seeders/UsersSeeder.php`).

| Rol        | Correo                          | Contraseña |
| ---------- | ------------------------------- | ---------- |
| admin      | `admin@helpdesk.com`            | `123456`   |
| supervisor | `supervisor@helpdesk.com`       | `123456`   |
| agent      | `agente.luciana@helpdesk.com`   | `123456`   |
| agent      | `agente.miguel@helpdesk.com`    | `123456`   |
| client     | `cliente.andrea@helpdesk.com`   | `123456`   |
| client     | `cliente.bruno@helpdesk.com`    | `123456`   |

El login se hace en `/login` y la SPA guarda un token Sanctum, así que conviene abrir una
ventana de incógnito por rol para comparar las vistas sin que interfiera la sesión anterior.

### Qué ve cada rol

Los permisos se aplican en el API, no solo ocultando botones en la interfaz.

| Rol        | Alcance                                                                        |
| ---------- | ------------------------------------------------------------------------------ |
| `client`   | Solo los tickets que él mismo creó. No ve los comentarios internos. Puede calificar la atención. |
| `agent`    | Los tickets de su departamento o los que tiene asignados. Ve comentarios internos. No accede a métricas, reportes, reglas de SLA ni administración. |
| `supervisor` | Acceso a todos los tickets. Métricas del panel, reportes (incluida la exportación a Excel), reglas de SLA y gestión de usuarios. |
| `admin`    | Todo lo de `supervisor` y además la gestión de departamentos, que es exclusiva de `admin`. |

Para comprobar los contrastes entre roles, entra como `cliente.andrea` (solo ve lo suyo), luego
como `agente.luciana` (además ve los internos del departamento *Soporte Técnico*) y finalmente
como `admin` (todo el sistema).

---

## Comandos habituales

```bash
docker compose ps                     # estado de los contenedores
docker compose logs -f app            # logs de la aplicación
docker compose exec app php artisan   # cualquier comando artisan
docker compose down                   # parar (conserva los datos)
docker compose down -v                # parar y BORRAR la base de datos
docker compose up -d --build          # tras cambiar código o dependencias
```

---

## Servicios

| Servicio    | Función                                                              |
| ----------- | -------------------------------------------------------------------- |
| `app`       | nginx + php-fpm. Es el único con puerto publicado (`8000`).           |
| `queue`     | Worker de colas: notificaciones y exportaciones.                      |
| `scheduler` | Planificador. Ejecuta `tickets:check-slas` cada hora.                 |

`queue` y `scheduler` son necesarios porque la app usa `database` para colas, sesiones y caché.

---

## Desarrollo con hot reload

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
```

- App en <http://localhost:8000> con `APP_DEBUG=true`
- Vite en <http://localhost:5173>: los cambios en `resources/` se ven al instante en el navegador
- El código del host se monta en el contenedor, así que no hay que reconstruir la imagen
- `composer install` (con dependencias de desarrollo) y `npm install` se ejecutan automáticamente
  al arrancar; si borras los volúmenes, se vuelven a instalar

Cambiar de modo:

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml down
docker compose up -d
```

Si en tu máquina el puerto 5173 está ocupado, cambia el origen de los assets:

```bash
VITE_DEV_ORIGIN=http://localhost:5174 \
  docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
```

---

## Configuración

Los valores por defecto funcionan sin configurar nada. Se pueden cambiar por variable de entorno
al lanzar (Compose las toma de tu `.env` local si existe):

| Variable        | Por defecto                | Para qué                          |
| --------------- | -------------------------- | --------------------------------- |
| `APP_PORT`      | `8000`                     | Puerto publicado en el host       |
| `APP_URL`       | `http://localhost:8000`    | URL pública de la app             |
| `APP_TIMEZONE`  | `UTC`                      | Zona horaria                      |
| `VITE_PORT`     | `5173`                     | Puerto del dev server (modo dev)  |
| `VITE_DEV_ORIGIN` | `http://localhost:5173`  | URL pública del dev server        |

Para cambiar algo de la aplicación de forma permanente, edita
`.env.docker.example` (es la plantilla que el contenedor copia a `.env` al arrancar por primera vez).

> **Ojo:** Docker Compose también lee el `.env` de Laravel para resolver variables. Por eso
> `APP_ENV`, `APP_DEBUG` y `APP_URL` están fijos en `docker-compose.yml`: si se dejaran como
> `${APP_ENV:-production}`, un `.env` local con `APP_ENV=local` acabaría mandando sobre el
> contenedor.

---

## Dónde viven los datos

Dos volúmenes de Docker, así que los datos sobreviven a `down` y a la reconstrucción de la imagen:

| Volumen               | Contenido                                                  |
| --------------------- | ---------------------------------------------------------- |
| `app_database`        | Base de datos SQLite (`/data/database.sqlite`)              |
| `app_storage`         | Adjuntos de tickets, logs y vistas compiladas               |

Para empezar de cero, `docker compose down -v` borra ambos.

---

## Stack

- PHP 8.5 · Laravel 13 · Sanctum 4 · maatwebsite/excel
- Node 22 · Vite 8 · Vue 3 · Pinia · Vue Router · Tailwind CSS 4
- nginx + php-fpm sobre Alpine, supervisado con supervisor
- PHPUnit · Laravel Pint

### Extensiones de PHP compiladas en la imagen

La imagen `php:8.5-fpm-alpine` ya trae `ctype`, `curl`, `dom`, `fileinfo`, `iconv`, `mbstring`,
`pdo`, `pdo_sqlite`, `posix`, `session`, `simplexml`, `tokenizer`, `xml` y `opcache`. El Dockerfile
compila solo las que faltan: `bcmath`, `exif`, `gd`, `intl`, `pcntl`, `pdo_pgsql` y `zip`.

`pdo_pgsql` está incluida por si en algún momento quieres apuntar a un PostgreSQL externo, aunque
la configuración por defecto usa SQLite.

---

## Desarrollo sin Docker

Si prefieres trabajar con PHP y Node en el host:

```bash
composer setup     # instala dependencias, .env, APP_KEY, migraciones y assets
composer run dev
```

---

## Estructura de la configuración de Docker

```
docker/
├── app/Dockerfile          imagen multi-etapa (assets -> vendor -> runtime)
├── entrypoint.sh           .env, APP_KEY, SQLite, migraciones y cachés
├── nginx/default.conf      vhost, SPA, límites de subida y caché de assets
├── php/app.ini             límites de PHP y opcache
└── supervisor/             nginx + php-fpm
```

---

## Licencia

MIT

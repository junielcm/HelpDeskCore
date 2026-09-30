#!/bin/sh
# =============================================================================
# HelpDeskCore - entrypoint del contenedor
#
# Deja la aplicacion lista para servir: .env, APP_KEY, base de datos SQLite,
# permisos de escritura, migraciones y caches.
#
# Variables opcionales (ver docker-compose.yml):
#   RUN_MIGRATIONS  ejecutar `artisan migrate` al arrancar   (por defecto true)
#   RUN_SEEDERS     ejecutar `artisan db:seed` al arrancar    (por defecto false)
#   RUN_CACHE       construir cachés de config/rutas/vistas   (por defecto true)
#   DB_DATABASE     ruta del fichero SQLite                  (por defecto /data/database.sqlite)
# =============================================================================
set -e

cd /var/www/html

APP_DIR=/var/www/html
DB_FILE="${DB_DATABASE:-/data/database.sqlite}"

log() { printf '[entrypoint] %s\n' "$1"; }

# --- 1. Base de datos SQLite -------------------------------------------------
mkdir -p "$(dirname "$DB_FILE")"
if [ ! -f "$DB_FILE" ]; then
    log "creando base de datos SQLite en $DB_FILE"
    touch "$DB_FILE"
fi
chown www-data:www-data "$DB_FILE" 2>/dev/null || true

# --- 2. Fichero .env ---------------------------------------------------------
if [ ! -f .env ]; then
    log "generando .env a partir de .env.docker.example"
    cp .env.docker.example .env
fi

# --- 3. APP_KEY --------------------------------------------------------------
if ! grep -qE '^APP_KEY=.+$' .env; then
    log "generando APP_KEY"
    php artisan key:generate --force --no-interaction
fi

# --- 4. Permisos de escritura ----------------------------------------------
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/app/public \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# --- 5. Enlace publico de storage ------------------------------------------
if [ ! -e public/storage ]; then
    php artisan storage:link --force --no-interaction 2>/dev/null \
        || log "aviso: no se pudo crear el enlace public/storage"
fi

# --- 6. Migraciones ---------------------------------------------------------
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    log "ejecutando migraciones"
    php artisan migrate --force --no-interaction
fi

if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    log "ejecutando seeders"
    php artisan db:seed --force --no-interaction
fi

# --- 7. Optimizacion de cachés ---------------------------------------------
if [ "${RUN_CACHE:-true}" = "true" ]; then
    if [ "${APP_ENV:-production}" = "production" ]; then
        log "construyendo cachés de producción"
        php artisan config:cache --no-interaction
        php artisan route:cache --no-interaction
        php artisan event:cache --no-interaction
        php artisan view:cache --no-interaction
    else
        php artisan config:clear --no-interaction >/dev/null 2>&1 || true
        php artisan route:clear --no-interaction >/dev/null 2>&1 || true
        php artisan view:clear --no-interaction >/dev/null 2>&1 || true
    fi
fi

log "listo: APP_ENV=${APP_ENV:-production} APP_DEBUG=${APP_DEBUG:-false}"

# --- 8. Comando final (supervisord, queue:work, schedule:work, ...) ---------
exec "$@"

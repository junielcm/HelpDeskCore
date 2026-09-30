# syntax=docker/dockerfile:1.7

# =============================================================================
# HelpDeskCore - Imagen de produccion (nginx + php-fpm)
#
#   docker compose up -d --build          # produccion
#   docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d   # desarrollo
# =============================================================================

# -----------------------------------------------------------------------------
# Stage 1 - Assets de frontend (Vite)
# -----------------------------------------------------------------------------
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY . .

RUN npm run build \
    && rm -rf node_modules

# -----------------------------------------------------------------------------
# Stage 2 - Dependencias PHP (Composer, sin dev)
# -----------------------------------------------------------------------------
FROM composer:2.8 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# ext-gd no viene en la imagen oficial de composer, pero phpoffice/phpspreadsheet
# (viene con maatwebsite/excel) la exige. La imagen final si la compila, asi que
# aqui solo se ignora el requisito de plataforma: no se ejecuta la aplicacion.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --ignore-platform-req=ext-gd

COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && php artisan package:discover --ansi

# -----------------------------------------------------------------------------
# Stage 3 - Runtime
# -----------------------------------------------------------------------------
FROM php:8.5-fpm-alpine AS runtime

LABEL org.opencontainers.image.title="HelpDeskCore" \
      org.opencontainers.image.description="Sistema de gestion de tickets / mesa de ayuda (Laravel + Vue)"

ENV APP_ENV=production \
    APP_DEBUG=false \
    COMPOSER_ALLOW_SUPERUSER=1 \
    PATH="/var/www/html/vendor/bin:${PATH}"

# nginx + supervisor + librerias de runtime de las extensiones
RUN apk add --no-cache \
        nginx \
        supervisor \
        tzdata \
        curl \
        icu-libs \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
        oniguruma \
        libxml2 \
        libpq

# dependencias de compilacion (se eliminan al final)
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        curl-dev \
        icu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        oniguruma-dev \
        libxml2-dev \
        postgresql-dev

# Ext-gd requiere libpng, libjpeg y freetype.
# Solo se compila lo que la imagen base php:8.5-fpm-alpine no trae ya:
# ctype, curl, dom, fileinfo, iconv, mbstring, pdo, pdo_sqlite, posix,
# session, simplexml, tokenizer, xml, xmlreader, xmlwriter y opcache.
# libpq se conserva en runtime porque lo necesita pdo_pgsql.so.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        pcntl \
        pdo_pgsql \
        zip \
    && apk del --no-network .build-deps \
    && rm -rf /tmp/*

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/php/app.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/supervisor/app.conf /etc/supervisor/conf.d/app.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p \
        /var/www/html/storage/framework/cache/data \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/logs \
        /var/www/html/storage/app/public \
        /var/www/html/bootstrap/cache \
        /data \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /data \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]

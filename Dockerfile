# syntax=docker/dockerfile:1

# ─────────────────────────────────────────────────────────────
# Stage 1 — build front-end assets (Vite + Tailwind)
# ─────────────────────────────────────────────────────────────
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# ─────────────────────────────────────────────────────────────
# Stage 2 — runtime: PHP-FPM + Nginx in a single container
# ─────────────────────────────────────────────────────────────
FROM php:8.4-fpm-alpine AS app

# System packages + PHP extensions the app needs (Postgres, GD, intl, zip…)
RUN apk add --no-cache \
        nginx \
        gettext \
        libpng-dev libjpeg-turbo-dev freetype-dev \
        icu-dev \
        libzip-dev \
        postgresql-dev \
        oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_pgsql pgsql gd intl zip opcache bcmath \
    && rm -rf /var/cache/apk/*

# Composer binary
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Custom PHP settings (opcache, upload limits for food photos)
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

# Install PHP dependencies first for better layer caching
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Application source
COPY . .

# Built assets from stage 1
COPY --from=assets /app/public/build ./public/build

# Finalize autoloader, ensure writable runtime dirs, set permissions
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Nginx config template + container entrypoint
COPY docker/nginx.conf.template /etc/nginx/nginx.conf.template
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Render (and most PaaS) inject $PORT; default to 8080 locally
ENV PORT=8080
EXPOSE 8080

ENTRYPOINT ["entrypoint"]

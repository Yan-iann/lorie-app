#!/bin/sh
set -e

# Default port for local `docker run`; Render/Fly override $PORT.
: "${PORT:=8080}"
export PORT

# Substitute the runtime port into the Nginx config (only ${PORT} is expanded,
# so Nginx's own $uri / $query_string variables are left untouched).
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# Make sure writable runtime directories exist (they may be excluded from build).
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Cache framework config/routes/views for production performance.
# These read the environment variables provided by the host.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Apply outstanding database migrations.
php artisan migrate --force

# Start PHP-FPM in the background, then run Nginx in the foreground.
php-fpm -D
exec nginx -g 'daemon off;'

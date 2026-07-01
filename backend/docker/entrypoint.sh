#!/bin/sh
set -e

cd /var/www/html

# Ensure writable runtime dirs (named-volume mounts can reset ownership).
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Build package manifest (skipped at image build via --no-scripts).
php artisan package:discover --ansi || true

# Apply schema. Postgres readiness is guaranteed by compose healthcheck.
php artisan migrate --force

# Cache config & routes from the runtime environment (after env is injected).
php artisan config:cache
php artisan route:cache

exec "$@"

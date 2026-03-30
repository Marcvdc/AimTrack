#!/usr/bin/env sh
set -euo pipefail

cd /var/www/html

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Fix storage permissions for container user and make logging directories writable
chown -R $(id -u):$(id -g) storage/ bootstrap/cache/
chmod 777 storage/logs/ storage/framework/sessions/

if [ -f .env ]; then
    if ! grep -Eq '^APP_KEY=.+$' .env; then
        echo "[entrypoint] APP_KEY ontbreekt of is leeg in .env; genereer application key" >&2
        php artisan key:generate --force --ansi
    fi
fi

php artisan storage:link --ansi >/dev/null 2>&1 || true

if [ "${APP_ENV:-local}" = "local" ]; then
    php artisan filament:assets --ansi >/dev/null 2>&1 || true
fi

if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:clear --ansi >/dev/null 2>&1 || true
    php artisan config:cache --ansi
    php artisan route:cache --ansi
    php artisan view:cache --ansi
    php artisan event:cache --ansi >/dev/null 2>&1 || true
fi

exec "$@"

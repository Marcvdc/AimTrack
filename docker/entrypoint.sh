#!/usr/bin/env sh
set -euo pipefail

cd /var/www/html

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

if [ -f .env ]; then
    if ! grep -Eq '^APP_KEY=.+$' .env; then
        echo "[entrypoint] APP_KEY ontbreekt of is leeg in .env; genereer application key" >&2
        php artisan key:generate --force --ansi
    fi
fi

php artisan storage:link --ansi >/dev/null 2>&1 || true

# FilamentCopilot publiceert zijn CSS/JS naar public/vendor/filament-copilot via
# vendor:publish (niet via filament:assets). Zonder deze stap rendert de
# AI-chat-widget niet. In alle omgevingen draaien (idempotent met --force).
php artisan vendor:publish --tag=filament-copilot-assets --force --ansi >/dev/null 2>&1 || true

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

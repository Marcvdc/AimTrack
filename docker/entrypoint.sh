#!/usr/bin/env sh
set -euo pipefail

cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
    echo "[entrypoint] APP_KEY ontbreekt; genereer tijdelijke key voor deze container" >&2
    php artisan key:generate --force --ansi
fi

php artisan storage:link --ansi >/dev/null 2>&1 || true

if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:clear --ansi >/dev/null 2>&1 || true
    php artisan config:cache --ansi
    php artisan route:cache --ansi
    php artisan view:cache --ansi
    php artisan event:cache --ansi >/dev/null 2>&1 || true
fi

exec "$@"

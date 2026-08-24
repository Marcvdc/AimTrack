#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# De applicatiecode wordt gedeeld met de nginx-container via het named volume
# app_code. Docker vult een named volume alleen bij de éérste keer aanmaken uit
# de image; bij volgende deploys blijft de oude code staan. Daarom synct deze
# entrypoint de in de image gebakken pristine kopie (/opt/aimtrack) bij elke
# boot naar /var/www/html. storage/ en bootstrap/cache hebben eigen volumes en
# worden expliciet uitgesloten zodat hun state behouden blijft.
#
# De app- en queue-container delen dit volume en draaien allebei deze entrypoint;
# een flock op het volume serialiseert de sync zodat ze elkaar niet halverwege
# overschrijven en de queue pas verder gaat als de code volledig vers is.
if [ -d /opt/aimtrack ]; then
    {
        flock 9
        rsync -a --delete \
            --exclude '/.app-sync.lock' \
            --exclude '/storage/' \
            --exclude '/bootstrap/cache/' \
            /opt/aimtrack/ /var/www/html/
    } 9>/var/www/html/.app-sync.lock
fi

# storage/app/private is de root van de default filesystem-disk (FILESYSTEM_DISK=local)
# en storage/app/public die van de publieke disk. Beide staan niet in git — storage/
# bevat alleen .gitignore-bestanden — dus ze ontbreken in het image en daarmee in een
# vers app_storage-volume. Laravel maakt ze bij de eerste write zelf aan, maar alléén
# als de parent voor www-data schrijfbaar is. Door ze hier expliciet aan te maken vallen
# ze onder de chown/chmod hieronder en faalt een niet-schrijfbaar volume hard bij boot,
# in plaats van stil bij de eerste upload (dat gaf prod een 503 met storage_unwritable).
mkdir -p storage/app/private storage/app/public \
    storage/framework/cache storage/framework/sessions storage/framework/views \
    bootstrap/cache
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

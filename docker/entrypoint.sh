#!/usr/bin/env bash
set -euo pipefail

# In het image is de applicatieroot altijd /var/www/html. De override bestaat
# zodat tests/Unit/EntrypointScriptTest.php dit script in een sandbox kan draaien,
# net zoals de deploy-scripts DEPLOY_PATH gebruiken.
APP_ROOT="${APP_ROOT:-/var/www/html}"

cd "${APP_ROOT}"

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
            /opt/aimtrack/ "${APP_ROOT}/"
    } 9>"${APP_ROOT}/.app-sync.lock"
fi

# storage/app/private is de root van de default filesystem-disk (FILESYSTEM_DISK=local)
# en storage/app/public die van de publieke disk. Beide staan niet in git (storage/
# bevat alleen .gitignore-bestanden), dus ze ontbreken in het image en daarmee in een
# vers app_storage-volume. Laravel maakt ze bij de eerste write zelf aan, maar alleen
# als de parent voor www-data schrijfbaar is. Door ze hier expliciet aan te maken vallen
# ze onder de chown/chmod onderaan, zodat www-data er altijd in kan schrijven. Lukt zelfs
# het aanmaken niet (bijvoorbeeld een read-only volume), dan stopt de container bij boot
# in plaats van stil te falen bij de eerste upload.
mkdir -p storage/app/private storage/app/public \
    storage/framework/cache storage/framework/sessions storage/framework/views \
    bootstrap/cache

# bootstrap/cache is een eigen volume en blijft dus over van de vorige build.
# packages.php en services.php noemen de service providers van die build; is een
# pakket sindsdien verdwenen, dan valt de eerste artisan over een ontbrekende
# klasse en blijft de container herstarten (#181). Alles hierin is afgeleid:
# Laravel bouwt de providerlijsten bij de volgende artisan opnieuw op uit vendor.
# De config-, route- en event-caches maakt het production-blok hieronder opnieuw;
# in andere omgevingen draait de app zonder die caches.
rm -f bootstrap/cache/*.php

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

# Na de artisan-caches, zodat ook wat die als root aanmaken (bootstrap/cache,
# storage/framework) van www-data is.
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Alleen php-fpm start als root: de master zakt zelf naar www-data (www.conf).
# Elk ander commando, zoals queue:work, krijgt geen eigen userwissel en zou als
# root jobs uitpakken met unserialize(). Die gaan via setpriv naar www-data.
# ENTRYPOINT_AS_ROOT=1 is de ontsnapping voor beheer. Draait de container al niet
# als root, dan valt er niets te wisselen. setpriv laat de environment staan, dus
# HOME gaat expliciet naar die van www-data in plaats van /root.
if [ "${1:-}" = "php-fpm" ] || [ "${ENTRYPOINT_AS_ROOT:-0}" = "1" ] || [ "$(id -u)" != "0" ]; then
    exec "$@"
fi

HOME=/var/www exec setpriv --reuid=www-data --regid=www-data --init-groups "$@"

# Infra

Overzicht van de infrastructuur en lokale ontwikkelsetup.

## Containers (Docker Compose)
- **app**: PHP 8.4/8.5 FPM met Laravel, Composer, extensies (pdo_pgsql/pdo_mysql, gd, mbstring, intl, zip, exif). Mountt repo naar `/var/www/html`.
- **web**: nginx die `/public/index.php` via FastCGI naar `app:9000` stuurt.
- **db**: PostgreSQL (default). MySQL-alternatief kan met eigen service worden toegevoegd.
- **queue**: zelfde image als `app`, draait `php artisan queue:work --tries=3`.

## Netwerk & poorten
- Web luistert op `:8080` (kan via compose override). DB standaard `5432`. Queue worker is intern.

## Configuratie (.env)
Belangrijkste variabelen:
- `APP_ENV`, `APP_DEBUG=false` voor productie.
- `APP_URL=http://localhost:8080` (dev) / prod-URL.
- `DB_CONNECTION=pgsql`, `DB_HOST=db`, `DB_PORT=5432`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
- `QUEUE_CONNECTION=database` (default).
- AI: `AI_DRIVER`, `AI_MODEL`, `OPENAI_API_KEY`, `OPENAI_BASE_URL`.
- Logging/monitoring: Sentry DSN indien gebruikt.

## Lifecycle
1. `docker compose up -d` start stack.
2. `docker compose exec app composer install` (eerste keer) en `php artisan key:generate`.
3. `docker compose exec app php artisan migrate --seed` om schema op te zetten.
4. Queue worker draait automatisch; anders handmatig starten met `docker compose exec queue php artisan queue:work`.

## Caching en assets
- Gebruik `php artisan config:cache`, `route:cache`, `view:cache` voor productie (entrypoint kan dit doen).
- Vite build (indien front assets) via `npm install && npm run build` buiten scope van Filament-only UI.

## Monitoring & health
- Health endpoint `/health` controleert DB, queue-config en storage schrijfbaarheid; te gebruiken voor container probes.
- Logs gaan naar stdout/stderr (container-friendly) en/of Sentry wanneer geconfigureerd.

## Backups
- Zie `docs/BACKUPS.md` voor strategie (pg_dump + storage sync). Plan cron/automation buiten de app; minimaal dagelijkse DB-dump en storage sync naar object storage.

## Deploy
- Multi-stage Docker image mogelijk: builder (composer install zonder dev) + runtime (php-fpm + opcache).
- GitHub Actions workflow kan image naar GHCR pushen; zie CI-notities in `docs/PLAN.md` sectie "Release & productiehardening".

# Application Hardened Checklist

Gebruik deze checklist voor productie-uitrol van AimTrack.

## Core instellingen
- [ ] `.env` gevuld met productiegegevens (`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` op publieke hostname).
- [ ] `APP_KEY` gezet via geheime variabele of secrets (niet hardcoderen in image). Eventuele vorige keys in `APP_PREVIOUS_KEYS`.
- [ ] `TRUSTED_PROXIES` afgestemd op load balancer/VPN ranges; `APP_FORCE_HTTPS=true` indien TLS via proxy.
- [ ] Databasegebruiker met minimale rechten (alleen SELECT/INSERT/UPDATE/DELETE op AimTrack-schema).

## Cache & performance
- [ ] `php artisan optimize` (config/route/view/event cache) uitgevoerd in de container.
- [ ] Opcache actief (controleer `opcache.enable=1`, validate timestamps uit in productie).
- [ ] Queue worker draait met `queue:work --tries=3 --backoff=5` en `restart: unless-stopped`.
- [ ] Queue worker draait als `www-data`, niet als root: `docker compose -f docker/compose.prod.yml --env-file .env top queue` toont in de kolom `UID` `www-data` (vanaf de host, dus zonder `ps` in de image; die heeft geen procps). De entrypoint start alleen `php-fpm` als root (de master zakt zelf naar `www-data`); elk ander commando gaat via `setpriv` naar `www-data`. `ENTRYPOINT_AS_ROOT=1` laat een beheercommando bewust als root draaien.

## Veiligheid & netwerk
- [ ] TLS beëindigd op load balancer/reverse proxy; `X-Forwarded-*` headers doorgeven en proxies vertrouwd.
- [ ] `AppServiceProvider` forceert HTTPS in productie; 4xx/5xx logging naar centraal logkanaal.
- [ ] Storage permissies gecontroleerd (`storage/`, `bootstrap/cache/` schrijfbaar door web user, geen world-writes).
- [ ] Uploads op juiste disk (bij voorkeur S3/secure bucket) of lokale opslag afgeschermd via webserver.

## AI & externe diensten
- [ ] AI-provider key beschikbaar als secret; timeouts/retries geactiveerd in `ShooterCoach`.
- [ ] Fallback copy gecontroleerd bij API-fouten/timeout.
- [ ] Mailer ingesteld voor notificaties (SMTP host, poort, credentials) indien gebruikt.

## Data & backup
- [ ] Database backups (snapshot of dump) gepland en getest.
- [ ] `pg_dump` in de image past bij de server: `docker compose -f docker/compose.prod.yml --env-file .env exec backup pg_dump --version` toont 16.x bij `postgres:16`. Een oudere client weigert een nieuwere server en laat een leeg `.sql` achter. De image haalt `postgresql-client-16` uit de PGDG-repository; gaat de database naar een nieuwe major, dan gaat de client in de `Dockerfile` mee.
- [ ] Logretentie ingesteld (bijv. Docker logging driver/central log shipper).
- [ ] Exporteerbare data (CSV/PDF) conform beleid opgeslagen/verwijderd na gebruik.

## Smoke test
- [ ] `docker compose up -d` met productie `.env` succesvol.
- [ ] Home, login, Filament, queue jobs en exports doorlopen op productie-URL met HTTPS.

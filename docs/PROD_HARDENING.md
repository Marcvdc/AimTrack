# Application Hardened Checklist

Gebruik deze checklist voor productie-uitrol van AimTrack.

## Core instellingen
- [ ] `.env` gevuld met productiegegevens (`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` op publieke hostname).
- [ ] `APP_KEY` gezet via geheime variabele of secrets (niet hardcoderen in image). Eventuele vorige keys in `APP_PREVIOUS_KEYS`.
- [ ] `TRUSTED_PROXIES` afgestemd op load balancer/VPN ranges; `APP_FORCE_HTTPS=true` indien TLS via proxy.
- [ ] Databasegebruiker met minimale rechten (alleen SELECT/INSERT/UPDATE/DELETE op AimTrack-schema).

## Proxy- en hostvertrouwen (vereist sinds de middleware-registratie hersteld is)

Tot PR #157 werd `app/Http/Kernel.php` niet geladen, waardoor `TrustHosts` niet in de stack zat en
`TrustProxies` niets vertrouwde. Beide doen nu wel hun werk, en dat maakt twee tot dan toe inerte
instellingen levend. Controleer ze bij het opzetten van de (nieuwe) productiehost:

- [ ] Controleer de **effectieve** `TRUSTED_PROXIES` in de draaiende container, niet alleen de waarde
      in `.env`. De root `docker-compose.yml` zet `TRUSTED_PROXIES` als container-environment en die
      overschrijft het `.env`-bestand: `docker compose exec app printenv TRUSTED_PROXIES`, en ter
      controle van wat de applicatie zelf ziet
      `docker compose exec app php artisan tinker --execute="print_r(config('trustedproxy.proxies'));"`.
- [ ] Die effectieve waarde mag **geen `*`** zijn. Met `*` vertrouwt de applicatie de
      `X-Forwarded-For` van elke aanroeper, dus wie de origin buiten de proxy om bereikt (#123) kan
      zijn client-IP vervalsen. Zet een concreet bereik dat past bij de netwerkopzet van de host,
      bijvoorbeeld een RFC1918-bereik zoals in `.env.example` (alleen als voorbeeldwaarde: dat
      bestand komt nooit op de productiehost terecht).
- [ ] Werk de waarde bij in de bron die bij de stack hoort, niet in het bestand op de host:
      - `docker/compose.prod.yml` leest `TRUSTED_PROXIES` uit `env_file: ../.env`. Er is geen
        automatische deploy meer die die `.env` overschrijft (zie
        `docs/plans/deploy-pipeline-uit-plan.md`); de bron is het bestand op de host. Op de
        homelab-VM zet de eigen compose de waarde in het `environment:`-blok, en die wint.
      - De root `docker-compose.yml` zet `TRUSTED_PROXIES` hard in het `environment:`-blok, en dat
        wint van `env_file`. Daar is het secret niet de bron; wijzig het `environment:`-blok.
- [ ] `APP_URL` staat op de hostnaam waarop de applicatie **daadwerkelijk** benaderd wordt.
      `TrustHosts` vertrouwt zonder argumenten alleen `APP_URL` en subdomeinen daarvan, en
      beantwoordt een afwijkende `Host`-header met 400. Staat `APP_URL` verkeerd, dan is de
      applicatie onbereikbaar.

De productiehost wordt opnieuw opgezet (paragraaf 11, fase E van het plan
`aimtrack-minimale-gebruiksset`). Deze drie punten horen bij de acceptatiecriteria van die nieuwe
opzet; ze blokkeren de merge van PR #157 niet, maar wel de eerste deploy erna.

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
- [ ] `AUTH_REGISTRATION_ENABLED` staat uit (default). Dan bestaat `/admin/register` niet (404). Zet je hem aan, dan is e-mailverificatie verplicht en moet de mailconfig werken. Accounts van vóór migratie `2026_10_17_083301_mark_existing_users_as_verified` zijn als geverifieerd gemarkeerd. Let op: met registratie uit wordt bij een verse install niemand vanzelf beheerder; zet `is_admin` dan zelf voor het eerste account.
- [ ] PHP-hardening op de FPM-pool (`docker/php/fpm-hardening.conf`): `exec`, `passthru`, `shell_exec`, `system`, `proc_open`, `popen` en `pcntl_exec` uit, `allow_url_fopen` uit. Alleen voor webrequests; de CLI (`queue:work`, `artisan`, de entrypoint) houdt ze, want Symfony Process heeft `proc_open` nodig. `expose_php = Off` via `docker/php/hardening.ini`.

### Controle van de PHP-hardening op een gebouwde image

```bash
docker build --target production -t aimtrack-prod:check .

# CLI: geen beperking
docker run --rm --entrypoint php aimtrack-prod:check -r 'var_dump(ini_get("disable_functions"), ini_get("allow_url_fopen"));'
# verwacht: string(0) "" en string(1) "1"

# FPM-pool: wel beperkt
docker run --rm --entrypoint php-fpm aimtrack-prod:check -tt 2>&1 | grep -E 'disable_functions|allow_url_fopen'
# verwacht: php_admin_value[allow_url_fopen] = 0 en de zeven functies
```

Wil je het in een echt FPM-request zien: start in een wegwerpcontainer `php-fpm -D`, installeer daar `libfcgi-bin` en vraag een scriptje op met `SCRIPT_FILENAME=/pad/naar/probe.php REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000`. `ini_get('disable_functions')` geeft dan de lijst terug en `exec()` faalt met `Call to undefined function`. Installeer `libfcgi-bin` niet in de image zelf.

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

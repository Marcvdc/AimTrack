# Productie-hardening voor de homelab-VM

Status: APPROVED (Marc, 2026-10-12)
Auteur: Claude (sessie in Marcvdc/homelab)
Datum: 2026-10-11, bijgewerkt 2026-10-12 (TrustProxies naar PR #157)
Aanleiding: uitrol van AimTrack op VM 202, zie Marcvdc/homelab#8

## 1. Context & Aanleiding

AimTrack draait sinds 2026-10-11 op een eigen VM in de homelab (Docker, `target: production`,
achter een Caddy-proxy op `192.168.1.13`). Bij de uitrol en de review daarvan kwamen drie
punten boven die in deze repo zitten en die AimTrack tegenhouden om aan het internet te gaan.

1. **De nachtelijke backup faalt.** De image installeert `postgresql-client` uit Debian
   bookworm, en dat is versie 15. De database is `postgres:16`. `pg_dump` 15 weigert een
   nieuwere server (`aborting because of server version mismatch`). `backup-dev-db.sh` laat
   dan een leeg `.sql` achter, dat de retentie (alleen `*.sql.gz`) nooit opruimt.
2. **`TRUSTED_PROXIES` wordt niet gelezen.** Laravel 12 gebruikt `bootstrap/app.php`;
   `app/Http/Kernel.php` wordt niet geladen, en daarmee ook `App\Http\Middleware\TrustProxies`
   niet. De framework-middleware draait wel, maar zonder proxies. Gevolg: elke bezoeker is het
   IP van de proxy, de login-throttle geldt voor iedereen samen, logs tonen geen client-IP, en
   `X-Forwarded-Proto` wordt genegeerd.
3. **De queue-worker draait als root.** De entrypoint moet als root starten (rsync naar het
   volume, `chown` van `storage/`), en eindigt met `exec "$@"` zonder userwissel. Voor
   `php-fpm` is dat goed (de master zakt zelf naar `www-data`), voor `php artisan queue:work`
   niet: die pakt jobs uit met `unserialize()`, als root. Bijeffect: maakt de queue als eerste
   `storage/logs/laravel.log` aan, dan is dat bestand van root en faalt php-fpm op elke
   logregel.

**Bijgesteld op 2026-10-12.** Punt 2 zit al in PR #157 (`harden(security): middleware-registratie
herstellen na Laravel 12-upgrade`, open sinds 2026-08-10). Die PR verwijdert de dode
`app/Http/Kernel.php` en `App\Http\Middleware\TrustProxies`, en laat de framework-middleware
`config/trustedproxy.php` lezen. Dit plan bouwde op diezelfde `App\Http\Middleware\TrustProxies`
voort en zou met #157 botsen. TrustProxies valt daarom uit dit plan: #157 reviewen en mergen is
de route. Dit plan doet punt 1 en 3.

## 2. Doelen

1. De nachtelijke dump van de dienst `backup` slaagt tegen `postgres:16`.
2. Alleen `php-fpm` start als root; elk ander commando via de entrypoint draait als `www-data`.

## 3. Scope & Grenzen

**In scope**
- `Dockerfile`: `postgresql-client-16` uit de PGDG-repository, in de `base`-stage.
- `docker/entrypoint.sh`: na de voorbereiding naar `www-data` voor alles behalve `php-fpm`,
  en de `chown` van `storage/` en `bootstrap/cache/` ná de artisan-caches.
- Pest-tests voor beide, en documentatie.

**Uit scope**
- TrustProxies, `bootstrap/app.php` en `app/Http/Kernel.php`: PR #157.
- De backup-container zelf (cron als root, `docker/backup/entrypoint.sh`): die moet root zijn
  om `/etc/cron.d` te schrijven, en draait geen applicatiecode.
- `compose.prod.yml` en de homelab-compose; die staan in Marcvdc/homelab.

## 4. Constraints & Assumpties

- `setpriv` (util-linux 2.38.1) zit in `php:8.4-fpm-bookworm`; nagekeken op 2026-10-11.
  Geen `gosu` nodig, dus geen extra package.
- `php-fpm` in de image heeft `user = www-data` en `group = www-data` in `www.conf`.
- PGDG: `https://apt.postgresql.org/pub/repos/apt bookworm-pgdg main`, sleutel
  `ACCC4CF8` van postgresql.org.
- De `dev`-stage bouwt op `base` en krijgt dus ook client 16. De dev-compose gebruikt al
  `postgres:16`.

## 5. Aanpak

### 5.1 pg_dump 16
In de `base`-stage: `gnupg` en `curl` erbij, de PGDG-sleutel naar
`/etc/apt/keyrings/pgdg.asc`, de bron met `signed-by`, en `postgresql-client-16` in plaats van
`postgresql-client`. Eén `RUN`-laag, `rm -rf /var/lib/apt/lists/*` aan het eind zoals nu.

### 5.2 Queue als www-data
Aan het eind van `docker/entrypoint.sh`:
- de `chown -R www-data:www-data storage bootstrap/cache` verhuist naar ná de artisan-caches,
  zodat ook wat die aanmaken van `www-data` is;
- als `$1` `php-fpm` is: `exec "$@"` zoals nu;
- anders: `exec setpriv --reuid=www-data --regid=www-data --init-groups "$@"`;
- ontsnapping voor beheer: `ENTRYPOINT_AS_ROOT=1` laat het commando als root draaien.

## 6. Acceptatiecriteria

1. `docker run --rm <image> pg_dump --version` toont 16.x.
2. De entrypoint met `php-fpm` als commando roept geen `setpriv` aan; met
   `php artisan queue:work` wel, met `--reuid=www-data`.
3. `chown` van `storage` draait ná de laatste `artisan`-cache in productie.

## 7. Tests

- `tests/Unit/EntrypointScriptTest.php`: uitbreiden met een `setpriv`-stub en een log van de
  volgorde (AC 2 en 3), in dezelfde sandbox-opzet als de bestaande tests.
- AC 1 is niet in Pest te testen: handmatig na de build, en in de PR-beschrijving.

## 8. Risico's

- **PGDG als extra bron.** Een tweede apt-repository in de build. Gesigneerd, en de
  officiële weg voor client 16 op bookworm.
- **Rechten in `storage/`.** Bestaande volumes met bestanden van root worden bij de start
  door de verplaatste `chown` rechtgezet.

## 9. Documentatie

- `docs/PROD_HARDENING.md`: beide punten.
- Registry in `.ai/guidelines/parallel-worktrees.md`.

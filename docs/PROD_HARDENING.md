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
      zijn client-IP vervalsen. Zet een concreet bereik dat past bij de netwerkopzet van de host;
      `.env.example` bevat het RFC1918-voorbeeld.
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
- [ ] Logretentie ingesteld (bijv. Docker logging driver/central log shipper).
- [ ] Exporteerbare data (CSV/PDF) conform beleid opgeslagen/verwijderd na gebruik.

## Smoke test
- [ ] `docker compose up -d` met productie `.env` succesvol.
- [ ] Home, login, Filament, queue jobs en exports doorlopen op productie-URL met HTTPS.

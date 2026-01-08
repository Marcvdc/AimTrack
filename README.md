# AimTrack

AimTrack is een moderne, self-hosted schietlog voor sportschutters. Registreer sessies (met wapens/afstanden), voeg bijlagen toe, krijg AI-reflecties en wapen-trends, stel vragen aan een AI-coach en genereer CSV/PDF-exports voor o.a. WM-4-achtige aanvragen. Gebouwd met Laravel 12, Filament 4 en Livewire 3.

## Kernfeatures
- **Sessies**: datum, baan/vereniging, locatie, tijden, notities, attachments en meerdere wapen × afstand entries.
- **Reflectie**: handmatige reflectie + AI-reflectie (samenvatting, went_well, needs_improvement, next_focus).
- **Wapenbeheer**: wapens met type/kaliber/serienummer/opslag; inzicht in gebruik per sessie.
- **AI-coach**: vrije vragen op basis van eigen sessies/wapens.
- **Export**: CSV/PDF per periode + wapenselectie met NL-disclaimer.
- **Infra**: Docker Compose (nginx, php-fpm, db, queue) en queue-jobs voor AI-taken.

## Stack
- PHP 8.4/8.5, Laravel 12
- Filament 4 + Livewire 3
- Database: PostgreSQL (default) of MySQL
- Queue: database driver (queue-worker container)

## Snel starten (Docker + WSL)
1. Kopieer `.env.local.example` naar `.env.local` (niet committen) en vul waarden in.  
   - `UID`/`GID`: gebruik `id -u` / `id -g` binnen WSL om permissies correct te houden. Defaults 1000 werken voor de meeste installaties.  
   - `COMPOSE_PROJECT_NAME=aimtrack_dev` voorkomt containernaam-conflicten.
2. Start stack: `docker compose -f docker/compose.dev.yml --env-file .env.local up -d`.
3. Installeer dependencies: `docker compose -f docker/compose.dev.yml --env-file .env.local exec app composer install`.
4. Genereer key: `docker compose -f docker/compose.dev.yml --env-file .env.local exec app php artisan key:generate`.
5. Migreer (en seed): `docker compose -f docker/compose.dev.yml --env-file .env.local exec app php artisan migrate --seed`.
6. Stop stack: `docker compose -f docker/compose.dev.yml --env-file .env.local down` (voeg `-v` toe om volumes op te ruimen).
7. Open `http://localhost:8080` vanuit Windows of WSL-browser en log in (standaard Laravel-auth). Gebruik Filament om sessies/wapens te beheren.

## AI-configuratie
- Zet `AI_DRIVER`, `AI_MODEL`, `OPENAI_API_KEY` en `OPENAI_BASE_URL` (of andere provider variabelen) in `.env`.
- AI-calls verlopen via queue-jobs (`GenerateSessionReflectionJob`, `GenerateWeaponInsightJob`). Zorg dat de `queue` container draait.
- Prompts bevatten NL-disclaimers; AI-output is adviserend en vervangt geen veiligheidsregels of instructeurs.

## Export
Gebruik de Filament **Export**-pagina om CSV of PDF te downloaden. Periode is verplicht; wapenselectie optioneel. Download bevat een disclaimer dat de gebruiker zelf verantwoordelijk blijft voor actuele WM-4-eisen.

## Documentatie
- Techniek: `docs/architecture.md`, `docs/ai.md`, `docs/export.md`, `docs/infra.md`, `docs/operations.md`, `docs/security.md`
- Gebruiker: `docs/user/quickstart.md`, `docs/user/sessions.md`, `docs/user/weapons.md`, `docs/user/export.md`, `docs/user/ai-coach.md`
- Overige plannen: `docs/PLAN.md`, `docs/BACKUPS.md`, `docs/PROD_HARDENING.md`

## Security & Responsible Disclosure
- Meld kwetsbaarheden via **security@aimrack.nl** met impact, reproduceerbare stappen en relevante logs.
- We bevestigen ontvangst binnen 2 werkdagen vanaf hetzelfde adres.
- Versleutel gevoelige details (PGP op aanvraag) en dien geen publiek issue in tot het probleem is opgelost.
- Zie `SECURITY.md` voor het volledige disclosure-proces en richtlijnen voor onderzoekers.

## Ontwikkelen
- Lint: `composer lint` (Laravel Pint).
- Tests: `composer test` (Pest/phpunit).
- Houd NL-labels aan in Filament en gebruik queue voor AI-taken.

## Local Dev (WSL)
- Vereisten: Windows 11, WSL2 (Ubuntu 22.04 aanbevolen), Docker Desktop met WSL-integratie, Node/npm alleen wanneer je Vite-assets bouwt.
- `.env.local.example` bevat alle aanbevolen variabelen voor lokaal gebruik, inclusief `UID`, `GID` en `COMPOSE_PROJECT_NAME`. Kopieer naar `.env.local` en pas waar nodig aan.
- Gebruik consistent `--env-file .env.local` zodat UID/GID en projectnaam ook gelden voor `docker compose exec`.
- Queue-worker draait als aparte service `queue`; Mailpit luistert op `http://localhost:8025` (UI) en `smtp://localhost:1025`.
- Xdebug toevoegen? Plaats een ini-bestand in `docker/php/conf.d` en herstart de `app`-container.

### Troubleshooting (WSL)
1. **Container name conflict**:  
   - Oorzaak: oude containers bestaan nog of hebben een andere projectnaam.  
   - Fix: `docker compose -f docker/compose.dev.yml --env-file .env.local down --remove-orphans && docker container prune`. Zet desnoods `COMPOSE_PROJECT_NAME` op een uniekere waarde voor parallelle projecten.
2. **UID/GID warnings / permissieproblemen**:  
   - Controleer `UID` en `GID` in `.env.local` (gebruik `id -u`, `id -g`).  
   - Run `sudo chown -R $(id -u):$(id -g) storage bootstrap/cache` binnen WSL als bestanden root-eigenaar zijn geworden.
3. **Poort al in gebruik (8080/8025/1025/5432)**:  
   - `netstat -tlnp | grep 8080` binnen WSL om conflicterende processen te vinden. Pas poortmapping aan in `.env.local` en `docker/compose.dev.yml` indien nodig.
4. **Clean rebuild** (na grote dependency updates):  
   - `docker compose -f docker/compose.dev.yml --env-file .env.local down -v`  
   - `docker compose -f docker/compose.dev.yml --env-file .env.local build --no-cache`  
   - `docker compose -f docker/compose.dev.yml --env-file .env.local up -d`

## Staging Deployment
- Branch: `staging` → workflow `.github/workflows/deploy-staging.yml`.
- GitHub Environment: **staging** met secrets:
  - `SSH_HOST` (WireGuard IP), `SSH_USER`, `SSH_PORT` (optioneel), `SSH_KEY` (PEM), `DEPLOY_PATH`
  - `APP_URL`, `ENV_FILE_B64` (base64 van `.env`), `GHCR_USERNAME`, `GHCR_TOKEN`, `WG_CONFIG`
- Flow: build & push image naar `ghcr.io/<owner>/aimtrack` met tags `<short_sha>` + `staging-latest`, installeer/start WireGuard in de workflow (config via `WG_CONFIG`), rsync `docker/` + `scripts/` naar de Pi, `remote_deploy.sh` draait `docker compose -f docker/compose.staging.yml pull/up`, optioneel migrations en healthcheck (`/health`). Tunnel wordt in een cleanup-stap weer afgesloten.
- Networking: `staging.aimtrack.nl` verwijst naar het publieke IP van de Pi; forward extern poort 8080 (of gewenste poort) naar de host `WEB_PORT` (default 8080). TLS is optioneel voor staging; verkeer loopt via VPN/forwarding.
- Concurrency: één staging deploy tegelijk; tag van de laatste succesvolle deploy wordt lokaal opgeslagen voor rollback.

## Production Deployment
- Branch: `main` → workflow `.github/workflows/deploy-production.yml` (vereist succesvolle CI-run).
- GitHub Environment: **production** met dezelfde secret-namen als staging (andere waarden, inclusief `WG_CONFIG`).
- Tags: `<short_sha>` + `prod-latest`.
- Compose file: `docker/compose.prod.yml` (image uit GHCR, volumes voor code/storage/bootstrap cache).
- Deploy stap gebruikt dezelfde scripts als staging; healthcheck via `APP_URL/health`. Publiek verkeer komt binnen op extern poort 18080 → forwarded naar webcontainer (inclusief HTTPS offload volgens hostconfig).

## Raspberry Pi / SSH & Runner Setup
- Maak user `deploy` (of vergelijkbaar), voeg toe aan `docker`-group en zet `PermitRootLogin no`, key-only auth in `~deploy/.ssh/authorized_keys`.
- Installeer Docker Engine + Compose plugin. Controleer dat `docker compose version` werkt voor de deploy user.
- Bereid `DEPLOY_PATH` (bijv. `/opt/aimtrack`) en zorg voor schrijfrechten voor `deploy`.
- Netwerk: als de Pi alleen op LAN bereikbaar is, gebruik een self-hosted runner met labels `self-hosted`, `linux` (en evt. `arm64`), of zorg voor VPN/Tailscale zodat GitHub-hosted runners via SSH kunnen verbinden.
- SSH firewall open voor GitHub runner of LAN-runner; `ssh-keyscan` wordt in workflows gebruikt voor known_hosts.

## Checklist (in te vullen)
- [ ] Secrets per Environment in GitHub: `SSH_HOST`, `SSH_USER`, `SSH_KEY`, `DEPLOY_PATH`, `APP_URL`, `GHCR_USERNAME`, `GHCR_TOKEN`, `WG_CONFIG`, optioneel `SSH_PORT`, `ENV_FILE_B64`.
- [ ] Raspberry Pi: deploy user + docker group, Docker/Compose geïnstalleerd, `DEPLOY_PATH` aangemaakt.
- [ ] Runner-keuze: GitHub-hosted (als host publiek bereikbaar) of self-hosted op het LAN/VPN.
- [ ] `.env.staging` en `.env.production` klaar op de server (of base64 in secrets).
- [ ] DNS/hostnames naar staging/production URL ingesteld; poorten afgestemd (`WEB_PORT` in `.env`, extern 8080 voor staging, 18080/443 voor productie).

## Licentie
MIT – zie [LICENSE](LICENSE).

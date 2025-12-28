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

## Snel starten (Docker)
1. Kopieer `.env.example` naar `.env.local` (niet committen) en vul dev-waarden in.
2. Start stack: `docker compose -f docker/compose.dev.yml --env-file .env.local up -d`.
3. Installeer dependencies: `docker compose -f docker/compose.dev.yml exec app composer install`.
4. Genereer key: `docker compose -f docker/compose.dev.yml exec app php artisan key:generate`.
5. Migreer (en seed): `docker compose -f docker/compose.dev.yml exec app php artisan migrate --seed`.
6. Open `http://localhost:8080` en log in (standaard Laravel-auth). Gebruik Filament om sessies/wapens te beheren.

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

## Ontwikkelen
- Lint: `composer lint` (Laravel Pint).
- Tests: `composer test` (Pest/phpunit).
- Houd NL-labels aan in Filament en gebruik queue voor AI-taken.

## Local Dev (WSL)
- Vereist: Docker Desktop + WSL2, `npm` alleen nodig als je Vite assets bouwt.
- Maak `.env.local` op basis van `.env.example` met o.a.:
  - `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://localhost:8080`
  - `DB_HOST=db`, `DB_PORT=5432`, `DB_DATABASE=aimtrack`, `DB_USERNAME=aimtrack`, `DB_PASSWORD=aimtrack`
  - `QUEUE_CONNECTION=database`
- Start/stop:
  - `docker compose -f docker/compose.dev.yml --env-file .env.local up -d`
  - `docker compose -f docker/compose.dev.yml --env-file .env.local down`
- Queue-worker draait als aparte service `queue`. Mailpit is beschikbaar op poorten `8025` (UI) en `1025` (SMTP).
- Xdebug kan optioneel worden toegevoegd via een eigen ini-drop-in op de app container.

## Staging Deployment
- Branch: `staging` → workflow `.github/workflows/deploy-staging.yml`.
- GitHub Environment: **staging** met secrets:
  - `SSH_HOST`, `SSH_USER`, `SSH_PORT` (optioneel), `SSH_KEY` (PEM), `DEPLOY_PATH`
  - `APP_URL`, `ENV_FILE_B64` (base64 van `.env`), `GHCR_USERNAME`, `GHCR_TOKEN`
- Flow: build & push image naar `ghcr.io/<owner>/aimtrack` met tags `<short_sha>` + `staging-latest`, rsync `docker/` + `scripts/` naar de Pi, `remote_deploy.sh` draait `docker compose -f docker/compose.staging.yml pull/up`, optioneel migrations en healthcheck (`/health`).
- Concurrency: één staging deploy tegelijk; tag van de laatste succesvolle deploy wordt lokaal opgeslagen voor rollback.

## Production Deployment
- Branch: `main` → workflow `.github/workflows/deploy-production.yml` (vereist succesvolle CI-run).
- GitHub Environment: **production** met dezelfde secret-namen als staging (andere waarden).
- Tags: `<short_sha>` + `prod-latest`.
- Compose file: `docker/compose.prod.yml` (image uit GHCR, volumes voor code/storage/bootstrap cache).
- Deploy stap gebruikt dezelfde scripts als staging; healthcheck via `APP_URL/health`.

## Raspberry Pi / SSH & Runner Setup
- Maak user `deploy` (of vergelijkbaar), voeg toe aan `docker`-group en zet `PermitRootLogin no`, key-only auth in `~deploy/.ssh/authorized_keys`.
- Installeer Docker Engine + Compose plugin. Controleer dat `docker compose version` werkt voor de deploy user.
- Bereid `DEPLOY_PATH` (bijv. `/opt/aimtrack`) en zorg voor schrijfrechten voor `deploy`.
- Netwerk: als de Pi alleen op LAN bereikbaar is, gebruik een self-hosted runner met labels `self-hosted`, `linux` (en evt. `arm64`), of zorg voor VPN/Tailscale zodat GitHub-hosted runners via SSH kunnen verbinden.
- SSH firewall open voor GitHub runner of LAN-runner; `ssh-keyscan` wordt in workflows gebruikt voor known_hosts.

## Checklist (in te vullen)
- [ ] Secrets per Environment in GitHub: `SSH_HOST`, `SSH_USER`, `SSH_KEY`, `DEPLOY_PATH`, `APP_URL`, `GHCR_USERNAME`, `GHCR_TOKEN`, optioneel `SSH_PORT`, `ENV_FILE_B64`.
- [ ] Raspberry Pi: deploy user + docker group, Docker/Compose geïnstalleerd, `DEPLOY_PATH` aangemaakt.
- [ ] Runner-keuze: GitHub-hosted (als host publiek bereikbaar) of self-hosted op het LAN/VPN.
- [ ] `.env.staging` en `.env.production` klaar op de server (of base64 in secrets).
- [ ] DNS/hostnames naar staging/production URL ingesteld; poorten afgestemd (`WEB_PORT` in `.env`).

## Licentie
MIT – zie [LICENSE](LICENSE).

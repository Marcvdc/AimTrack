# CI/CD Plan voor AimTrack

> **Stand 2026-10-12:** de twee deploy-pipelines hieronder (staging en productie) zijn uitgezet.
> Ze deployden naar de Raspberry Pi, die gecompromitteerd is geweest. Productie wordt nu met de
> hand bijgewerkt op een VM in de homelab; zie de sectie Deployment in de README en
> `docs/plans/deploy-pipeline-uit-plan.md`. Alleen de CI-pipeline is nog actief. De rest van dit
> document beschrijft het oorspronkelijke ontwerp.

## Doel en uitgangspunten
- Laravel 12 + Filament 5 app met Docker-first deploystrategie.
- Branches: `staging` → staging deploy, `main` → productie deploy.
- Build een Docker image per commit, push naar GHCR, en deploy via remote Docker Compose op een Raspberry Pi.
- Gebruik GitHub Environments voor gescheiden secrets en gelijke strengheid tussen staging en productie.

## Pipelines
### CI (pull_request + push)
- Jobs: lint (Pint), audit (`composer audit --locked`, laat de run falen bij een security advisory en bij een abandoned package), test (Pest/PHPUnit + SQLite), optionele Node/Vite build (alleen als package-lock/pnpm-lock aanwezig).
- Caching: Composer cache + Node cache.
- PHP-versie: 8.4 (minimaal).

### Deploy staging (push → `staging`), uitgezet op 2026-10-12
- Environment: `staging` (secrets: SSH_HOST/USER/PORT/KEY, DEPLOY_PATH, APP_URL, ENV_FILE_B64 optioneel). GHCR gebruikt `github.actor` + de ingebouwde `GITHUB_TOKEN`, dus daar zijn geen losse secrets voor nodig.
- Build + push image naar GHCR met tags `<short_sha>` en `staging-latest`.
- Sync compose/shell scripts naar server, docker login, compose pull/up, optioneel artisan migrate, healthcheck via curl.
- Concurrency: één staging deploy tegelijk; rollback naar de vorige sha-tag gebeurt automatisch bij een falende migratie of healthcheck, en handmatig via `scripts/rollback.sh`.

### Deploy productie (push → `main`), uitgezet op 2026-10-12
- Environment: `production` (zelfde secret-namen, andere waarden).
- Zelfde flow als staging maar tags `<short_sha>` en `prod-latest`.
- Veiligheden: vereist geslaagde CI, Environment protections mogelijk, concurrency 1.

## Docker & Compose
- Multi-stage Dockerfile: builder (Composer + node/Vite indien aanwezig) → runtime (php-fpm + opcache). Entrypoint cachet config/routes/views in productie.
- Compose files per omgeving (geen impliciete overrides):
  - `docker/compose.dev.yml` (WSL lokale dev, volumes, Xdebug optioneel, mailpit, queue worker)
  - `docker/compose.staging.yml` (remote, image uit GHCR, mount storage/log volumes)
  - `docker/compose.prod.yml` (remote, identiek aan staging behalve labels/volumes)

## Deploy scripts (remote)
- `scripts/remote_deploy.sh`: ontvangt env-variabelen (PATH/SSH/IMAGE_TAG/REGISTRY_IMAGE/APP_URL/ENV_FILE_B64); login GHCR, docker compose pull/up, optioneel migrate, healthcheck, en rollback naar de vorige tag als migrate of healthcheck faalt.
- `scripts/rollback.sh`: losse ingang voor een handmatige rollback (`bash scripts/rollback.sh [tag]`, `--list` toont de stand).
- `scripts/healthcheck.sh`: curl naar APP_URL/health met timeout/retries.
- `scripts/migrate.sh`: artisan migrate --force in running container.

## Secrets & configuratie
- `.env.example` uitbreiden met deployment-variabelen placeholders.
- `.env.local` (niet committen) voor WSL dev; `.env.staging` en `.env.production` op server of via ENV_FILE_B64 secret.
- GHCR via de ingebouwde `GITHUB_TOKEN` van de workflow; SSH key-only auth, deploy user in docker group.

## Documentatie-updates
- README-secties voor Local Dev (WSL), Staging Deployment, Production Deployment, Raspberry Pi/SSH & runner setup.
- Checklist voor benodigdheden (secrets, hostnames, runner keuze, DEPLOY_PATH voorbereiden).

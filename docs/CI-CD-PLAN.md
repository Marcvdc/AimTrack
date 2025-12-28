# CI/CD Plan voor AimTrack

## Doel en uitgangspunten
- Laravel 12 + Filament 4 app met Docker-first deploystrategie.
- Branches: `staging` → staging deploy, `main` → productie deploy.
- Build een Docker image per commit, push naar GHCR, en deploy via remote Docker Compose op een Raspberry Pi.
- Gebruik GitHub Environments voor gescheiden secrets en gelijke strengheid tussen staging en productie.

## Pipelines
### CI (pull_request + push)
- Jobs: lint (Pint), test (Pest/PHPUnit + SQLite), optionele Node/Vite build (alleen als package-lock/pnpm-lock aanwezig).
- Caching: Composer cache + Node cache.
- PHP-versie: 8.4 (minimaal).

### Deploy staging (push → `staging`)
- Environment: `staging` (secrets: SSH_HOST/USER/PORT/KEY, DEPLOY_PATH, APP_URL, ENV_FILE_B64 optioneel, GHCR_USERNAME/TOKEN).
- Build + push image naar GHCR met tags `<short_sha>` en `staging-latest`.
- Sync compose/shell scripts naar server, docker login, compose pull/up, optioneel artisan migrate, healthcheck via curl.
- Concurrency: één staging deploy tegelijk; rollback via vorige sha-tag.

### Deploy productie (push → `main`)
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
- `scripts/remote_deploy.sh`: ontvangt env-variabelen (PATH/SSH/IMAGE_TAG/REGISTRY_IMAGE/APP_URL/ENV_FILE_B64); login GHCR, docker compose pull/up, optioneel migrate, healthcheck.
- `scripts/healthcheck.sh`: curl naar APP_URL/health met timeout/retries.
- `scripts/migrate.sh`: artisan migrate --force in running container.

## Secrets & configuratie
- `.env.example` uitbreiden met deployment-variabelen placeholders.
- `.env.local` (niet committen) voor WSL dev; `.env.staging` en `.env.production` op server of via ENV_FILE_B64 secret.
- GHCR credentials per Environment; SSH key-only auth, deploy user in docker group.

## Documentatie-updates
- README-secties voor Local Dev (WSL), Staging Deployment, Production Deployment, Raspberry Pi/SSH & runner setup.
- Checklist voor benodigdheden (secrets, hostnames, runner keuze, DEPLOY_PATH voorbereiden).

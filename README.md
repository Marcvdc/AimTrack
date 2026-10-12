# AimTrack

AimTrack is een moderne, self-hosted schietlog voor sportschutters. Registreer sessies (met wapens/afstanden), voeg bijlagen toe, krijg AI-reflecties en wapen-trends, stel vragen aan een AI-coach en genereer CSV- en PDF-exports voor je eigen verenigingsadministratie. Gebouwd met Laravel 12, Filament 5 en Livewire 4.

## Kernfeatures
- **Sessies**: datum, baan/vereniging, locatie, tijden, notities, attachments en meerdere wapen × afstand entries.
- **Reflectie**: handmatige reflectie + AI-reflectie (samenvatting, went_well, needs_improvement, next_focus).
- **Wapenbeheer**: wapens met type/kaliber/serienummer/opslag; inzicht in gebruik per sessie.
- **AI-coach**: vrije vragen op basis van eigen sessies/wapens.
- **Export**: CSV/PDF per periode + wapenselectie met NL-disclaimer.
- **Infra**: Docker Compose (nginx, php-fpm, db, queue) en queue-jobs voor AI-taken.

## Stack
- PHP 8.4/8.5, Laravel 12
- Filament 5 + Livewire 4
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
Gebruik de Filament **Export**-pagina om CSV of PDF te downloaden. Periode is verplicht; wapenselectie optioneel. Download bevat een disclaimer dat de gebruiker zelf verantwoordelijk blijft voor de actuele eisen.

## Documentatie
- Techniek: `docs/architecture.md`, `docs/ai.md`, `docs/export.md`, `docs/infra.md`, `docs/operations.md`, `docs/security.md`
- Gebruiker: `docs/user/quickstart.md`, `docs/user/sessions.md`, `docs/user/weapons.md`, `docs/user/export.md`, `docs/user/ai-coach.md`
- AI Agents: `docs/ai-agents.md` - Automated development with AI agents
- Overige plannen: `docs/PLAN.md`, `docs/BACKUPS.md`, `docs/PROD_HARDENING.md`

## Security & Responsible Disclosure
- Meld kwetsbaarheden via **security@aimtrack.nl** met impact, reproduceerbare stappen en relevante logs.
- We bevestigen ontvangst binnen 2 werkdagen vanaf hetzelfde adres.
- Versleutel gevoelige details (PGP op aanvraag) en dien geen publiek issue in tot het probleem is opgelost.
- Zie `SECURITY.md` voor het volledige disclosure-proces en richtlijnen voor onderzoekers.

## Ontwikkelen
- Lint: `composer lint` (Laravel Pint).
- Tests: `composer test` (Pest/phpunit).
- Houd NL-labels aan in Filament en gebruik queue voor AI-taken.

## AI Agents
AimTrack supports automated development via AI agents:

### GitHub Agent
- Gebruik `@claude` in issues om automatisch pull requests te laten genereren
- Agent analyseert de issue, implementeert de wijziging en creeërt een PR
- Werkt volgens projectconventies en voegt automatisch tests toe
- Zie `docs/ai-agents.md` voor gedetailleerde instructies

Voorbeeld:
```markdown
## Add Session Export Feature
@claude please add a CSV export feature for shooting sessions...
```

### Local Agent Environments
- Creëer geïsoleerde ontwikkel-omgevingen: `./scripts/clone-for-agent.sh agent-name`
- Elke agent heeft eigen Docker containers en git workspace
- Ideaal voor testing en development door AI agents
- Zie `docs/ai-agents.md` voor setup instructies

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

## Deployment
Productie draait sinds 2026-10-11 op een eigen VM in de homelab van Marc, achter een Caddy-proxy.
Een update gaat met de hand op die VM: `git pull`, `docker compose build`, `up -d`,
`package:discover` en `migrate --force`. De exacte commando's, de compose en de controles na de
uitrol staan in het runbook `docs/runbooks/aimtrack.md` in de (privé) repo `Marcvdc/homelab`.

Er is geen automatische deploy meer. De pipelines naar de oude Raspberry Pi (job
`deploy-production` in `ci.yml` en `deploy-staging.yml`) zijn op 2026-10-12 uitgezet, omdat die
host gecompromitteerd is geweest; zie `docs/plans/deploy-pipeline-uit-plan.md`. CI draait nog
wel: lint, audit, test en een build van de productie-image, zonder push. De test
`tests/Unit/GeenDeployWorkflowTest.php` faalt als er een deploy-workflow terugkomt; een nieuwe
pipeline naar de homelab-VM vraagt dus een eigen plan.

`scripts/remote_deploy.sh`, `scripts/rollback.sh` en `docker/compose.staging.yml` staan er nog,
voor als er zo'n pipeline komt. Geen workflow roept ze aan.

## Licentie
MIT – zie [LICENSE](LICENSE).

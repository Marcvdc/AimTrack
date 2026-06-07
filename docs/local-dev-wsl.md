# WSL Local Development Guide

Status: Reference  
Laatst bijgewerkt: 2026-01-05

Deze gids beschrijft de aanbevolen manier om AimTrack lokaal te draaien binnen Windows Subsystem for Linux (WSL2) met Docker. Volg de stappen in volgorde zodat nieuwe developers binnen ±15 minuten een werkende stack hebben.

## 1. Vereisten

1. Windows 11 met WSL2 (Ubuntu 22.04 aanbevolen).  
2. Docker Desktop geïnstalleerd en gekoppeld aan de gewenste WSL-distributie.  
3. Git, Composer en Node/npm (alleen nodig voor Vite-builds) in WSL.  
4. Toegang tot deze repository en eventuele secrets voor `.env.local`.

## 2. Environment file voorbereiden

1. Kopieer `.env.local.example` → `.env.local`.  
2. Vul/controleer minimaal deze variabelen:
   - `APP_KEY` (na `php artisan key:generate`).  
   - `UID` / `GID`: output van `id -u` / `id -g` binnen WSL.  
   - `COMPOSE_PROJECT_NAME`: standaard `aimtrack_dev`, kies iets unieks als je meerdere stacks draait.  
   - `WEB_PORT`, `DB_FORWARD_PORT`, `MAILPIT_HTTP_PORT`, `MAILPIT_SMTP_PORT`: wijzig bij poortconflicten op de host.  
   - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`: lokale DB-credentials (default `aimtrack`).  
3. Bewaar `.env.local` buiten versiebeheer (staat al in `.gitignore`).

> Tip: gebruik `printf "UID=%s\nGID=%s\n" "$(id -u)" "$(id -g)"` om waardes direct te kopiëren.

## 3. Stack starten en beheren

```bash
docker compose -f docker/compose.dev.yml --env-file .env.local up -d
docker compose -f docker/compose.dev.yml --env-file .env.local exec app composer install
docker compose -f docker/compose.dev.yml --env-file .env.local exec app php artisan key:generate
docker compose -f docker/compose.dev.yml --env-file .env.local exec app php artisan migrate --seed
```

Stoppen of opruimen:

```bash
docker compose -f docker/compose.dev.yml --env-file .env.local down
docker compose -f docker/compose.dev.yml --env-file .env.local down -v   # inclusief volumes
```

## 4. Validaties (voordat je gaat ontwikkelen)

1. **Services draaien**  
   ```bash
   docker compose -f docker/compose.dev.yml --env-file .env.local ps
   ```  
   Verwacht `app`, `web`, `db`, `queue`, `mailpit` allemaal in `Up` status.

2. **HTTP bereikbaar**  
   Open `http://localhost:${WEB_PORT}` of run `curl -I http://localhost:${WEB_PORT}` vanuit WSL.  
   Alternatief: `docker compose ... exec app php artisan route:list` om framework-boot te verifiëren.

3. **Database klaar**  
   ```bash
   docker compose -f docker/compose.dev.yml --env-file .env.local exec app php artisan migrate:status
   ```  
   Output moet elke migratie als **Yes** tonen.

4. **Queue worker actief**  
   ```bash
   docker compose -f docker/compose.dev.yml --env-file .env.local logs -f queue
   ```  
   Controleer dat `queue` luistert zonder errors.

5. **Mailpit**  
   Open `http://localhost:${MAILPIT_HTTP_PORT}`; UI moet zichtbaar zijn.  
   SMTP test: `docker compose ... exec app php artisan tinker` en stuur een testmail.

6. **Opschonen**  
   Gebruik `docker compose ... down --remove-orphans` als je wisselt tussen branches of je projectnaam aanpast.

Documenteer eventuele afwijkingen in `docs/infra.md` wanneer je nieuwe tools toevoegt.

## 5. Troubleshooting

| Issue | Symptoom | Fix |
|-------|----------|-----|
| Container name conflict | `Error: network aimtrack_dev_default already exists` | `docker compose -f docker/compose.dev.yml --env-file .env.local down --remove-orphans && docker container prune` of kies andere `COMPOSE_PROJECT_NAME`. |
| UID/GID permissies | Bestanden in `storage/` of `bootstrap/cache` zijn root-owned | Update `UID`/`GID` en run `sudo chown -R $(id -u):$(id -g) storage bootstrap/cache`. |
| Poorten bezet | `Bind for 0.0.0.0:8080 failed` | Kies andere `WEB_PORT` / `DB_FORWARD_PORT` / `MAILPIT_*` in `.env.local` en start stack opnieuw. |
| Trage bestands-I/O | Code staat op Windows-bestandssysteem | Clone repo in de WSL-ext4 filesystem (`/home/<user>/AimTrack`). |
| Database corrupt | `pg_isready` healthcheck faalt | `docker compose ... down -v && docker volume rm aimtrack_dev_db_data_dev` (let op: data kwijt). |

Zie ook de Troubleshooting-sectie in `README.md` voor aanvullende tips.

## 6. Handige commands

- Lint: `docker compose ... exec app composer lint`
- Tests: `docker compose ... exec app composer test`
- Vite build (optioneel): `docker compose ... exec app npm install && npm run build`
- Artisan vanuit host: `docker compose ... exec app php artisan <command>`
- Backup dev database:
  ```bash
  ./scripts/backup-dev-db.sh                      # eenmalige dump + gzip + 7d retention
  COMPOSE_FILE=docker/compose.dev.yml \
  ENV_FILE=.env.local \
  BACKUP_DIR=backups \
  ./scripts/backup-dev-db.sh                      # override voorbeelden
  ```
  Voeg voor dagelijks cron-job op WSL toe (`crontab -e`):
  ```
  0 3 * * * cd /home/<user>/CascadeProjects/AimTrack && ./scripts/backup-dev-db.sh >> backups/cron.log 2>&1
  ```
  Restoren:
  ```bash
  zcat backups/db-YYYYMMDD-HHMMSS.sql.gz | docker compose -f docker/compose.dev.yml --env-file .env.local exec -T db psql -U aimtrack -d aimtrack
  ```

Volg altijd de PLAN-FIRST richtlijnen: pas je `.env.local` aan vóórdat je containers start, en houd documentatie synchroon bij wijzigingen.

# Database Backup Runbook

## Overzicht
Staging en productie draaien dagelijkse PostgreSQL-back-ups via een dedicated `backup`-service in de respectieve Docker Compose stacks. De service gebruikt `scripts/backup-dev-db.sh` (in `direct` mode) met cron (`BACKUP_SCHEDULE`, standaard 03:00) en bewaart gzipte dumps op een persistent volume (`db_backups_<env>`).

## Architectuur
- **Container:** dezelfde applicatie-image (`php:8.4-fpm-bookworm`, Debian) met Vixie-`cron` en `postgresql-client` (zie `Dockerfile`).
- **EntryPoint:** `docker/backup/entrypoint.sh` (draait onder `bash`) schrijft een cron-job naar `/etc/cron.d/aimtrack-backup`, start de daemon met `cron -f` en logt naar `/backups/cron.log`. Op het Debian-image bestaan de Alpine-conventies `crond` en `/etc/crontabs/` niet.
- **Runner:** `docker/backup/run-backup.sh` (draait onder `bash`) roept `scripts/backup-dev-db.sh` aan met `BACKUP_MODE=direct`. In `direct` mode is dit een volwaardige productie-`pg_dump` tegen `DB_HOST`; de "dev"-naam is historisch.
- **Volumes:** `db_backups_staging` / `db_backups_prod` worden op `/backups` gemount en bevatten dumps + `cron.log`.
- **Database connectie:** environment variabelen (`DB_HOST=db`, `DB_PORT=5432`, …) verwijzen naar de interne `db`-service.
- **Env-propagatie:** Debian-cron geeft de container-environment niet door aan jobs. De entrypoint snapshot daarom de backup- en DB-variabelen (`BACKUP_MODE`, `BACKUP_DIR`, `DB_HOST`, `DB_PASSWORD`, …) als regels boven in `/etc/cron.d/aimtrack-backup`, zodat de geplande job met dezelfde configuratie draait als de container.

## Configuratie & Variabelen
| Variabele | Beschrijving | Default |
|-----------|--------------|---------|
| `BACKUP_PREFIX_STAGING` / `BACKUP_PREFIX_PROD` | Prefix voor bestandsnamen | `staging-db` / `prod-db` |
| `BACKUP_RETENTION_DAYS` | Aantal dagen dat dumps bewaard blijven (`find ... -mtime`) | `14` |
| `BACKUP_SCHEDULE` | Cron-expressie voor `cron` | `0 3 * * *` |
| `BACKUP_DIR` | Binnen-container pad voor dumps | `/backups` |
| `BACKUP_LOG` | Logbestand met joboutput | `/backups/cron.log` |
| `BACKUP_DRY_RUN` | Zet op `1` voor tests (geen pg_dump) | `0` |

Env placeholders zijn toegevoegd aan `.env.example`; pas ze aan in `.env` voor staging/prod.

## Operaties
1. **Handmatig draaien:**
   ```bash
   docker compose -f docker/compose.staging.yml run --rm \
     -e BACKUP_DRY_RUN=1 backup /usr/local/bin/run-backup
   ```
   Laat `BACKUP_DRY_RUN=0` om een echte dump te maken.
2. **Log controleren:** `docker compose -f docker/compose.staging.yml logs -f backup` of lees `/backups/cron.log` op het gedeelde volume.
3. **Retentie aanpassen:** wijzig `BACKUP_RETENTION_DAYS` in `.env` en herstart de `backup`-service.
4. **Hersteltest:**
   ```bash
   docker compose -f docker/compose.staging.yml exec db \
     pg_restore -d aimtrack_restore /backups/staging-db-YYYYMMDD-HHMMSS.sql.gz
   ```
   (Pak eerst uit met `gunzip` indien nodig.)

## Monitoring & Alerts
- Controleer dat `cron.log` dagelijks een "Done"-regel bevat.
- Voeg infra-monitoring toe voor volumegebruik en cron failures (bijv. tail `cron.log` of health endpoint).
- In lijn met C-PII-Logging blijven dumps lokaal op het backups-volume; offsite replicatie is optioneel en niet geautomatiseerd.

# Back-upstrategie AimTrack

Doel: eenvoudige, herstelbare back-ups voor database (PostgreSQL) en uploads (storage). Deze stappen zijn bedoeld voor self-hosted deployments; automatiseer waar mogelijk via cron/CI.

## Database (PostgreSQL)
- **Frequentie:** dagelijks (nacht) volledige `pg_dump` + optioneel 4-uurlijkse WAL-archivering bij zware belasting.
- **Command voorbeeld:**
  - `PGPASSWORD="$DB_PASSWORD" pg_dump -Fc -h $DB_HOST -p $DB_PORT -U $DB_USERNAME $DB_DATABASE > /backups/aimtrack_$(date +%F).dump`
- **Rotatie:** bewaar 7 dagelijkse dumps + 4 wekelijkse + 3 maandelijkse. Gebruik `find ... -mtime` voor pruning.
- **Versleuteling:** versleutel archieven met bijv. `age` of `gpg` voordat ze offsite gaan.
- **Offsite opslag:** sync `/backups` naar object storage (S3/Backblaze) met lifecycle policy (versies + retentie).
- **Herstel test:** maandelijks hersteltest naar een tijdelijke database:
  - `pg_restore -d aimtrack_restore -h $DB_HOST -p $DB_PORT -U $DB_USERNAME /backups/aimtrack_YYYY-MM-DD.dump`
  - Draai `php artisan migrate --env=testing` of applicatie smoke-test tegen restore DB.

## Storage (uploads)
- **Pad:** `storage/app` (default disk) en eventuele externe disks.
- **Frequentie:** dagelijks `rsync` naar back-up volume + wekelijkse sync naar object storage.
- **Command voorbeeld:**
  - `rsync -a --delete /var/www/html/storage/app/ /backups/storage/`
  - `aws s3 sync /backups/storage s3://<bucket>/aimtrack/storage --delete`
- **Controle:** periodieke checksum (`sha256sum`) op een steekproef van bestanden.
- **Rechten:** zorg dat back-up gebruiker alleen leesrechten op uploads heeft, schrijfrechten op back-up locatie.

## Config & secrets
- **Niet in repo:** `.env`, SSL keys en database credentials horen in een secrets manager of versleutelde storage.
- **Back-up:** sla een versleutelde kopie van `.env` op samen met back-up instructies en rotatie.

## Herstelprocedure (samenvatting)
1. Nieuwe omgeving klaarzetten (PHP/Laravel dependencies, storage directories, `.env`).
2. Database herstellen via `pg_restore` en daarna `php artisan migrate --force` indien schema-updates nodig zijn.
3. Storage back-up terugplaatsen naar `storage/app` (rechten + eigenaarschap herstellen).
4. Caches regenereren: `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
5. Queue workers en scheduler herstarten, healthcheck `/health` controleren.

## Monitoring & verificatie
- **Healthcheck:** `/health` endpoint monitort DB/queue/storage toegankelijkheid.
- **Logboek:** documenteer uitgevoerde back-ups (tijdstip, resultaat, locatie) en hersteltesten.
- **Alerting:** configureer alerts op mislukte back-ups en healthcheck failures via Sentry/monitoring.

## Productie installatie

De repo bevat twee scripts die op de productie-host (`madmin`) draaien:

- `scripts/backup-aimtrack.sh` — dagelijkse `pg_dump` van container `aimtrack_db` + `rsync` van `storage/app` + rotatie (7 dagelijks, 4 wekelijks, 3 maandelijks).
- `scripts/restore-test-aimtrack.sh` — maandelijkse hersteltest naar tijdelijke database `aimtrack_restore_test`, controleert tabel- en rijcounts.

### Installatie

```bash
# als madmin op de prod-host
cp /var/www/AimTrack/scripts/backup-aimtrack.sh       /home/madmin/backup-aimtrack.sh
cp /var/www/AimTrack/scripts/restore-test-aimtrack.sh /home/madmin/restore-test-aimtrack.sh
chmod +x /home/madmin/backup-aimtrack.sh /home/madmin/restore-test-aimtrack.sh
mkdir -p /home/madmin/aimtrack-backups/{db,storage,logs}
```

**Eigenaarschap:** beide scripts horen bij gebruiker `madmin` (de cron-eigenaar) en hebben `chmod +x`. De backup-doelmap (`/home/madmin/aimtrack-backups`) is alleen leesbaar voor `madmin`.

### Crontab (madmin)

```cron
0 3 * * *   /home/madmin/backup-aimtrack.sh        >> /home/madmin/aimtrack-backups/logs/cron.log 2>&1
0 4 1 * *   /home/madmin/restore-test-aimtrack.sh  >> /home/madmin/aimtrack-backups/logs/cron.log 2>&1
```

- 03:00 dagelijks → backup.
- 04:00 op de 1e van de maand → hersteltest.
- Beide scripts schrijven hun eigen dagrotatie-logbestand in `/home/madmin/aimtrack-backups/logs/`, plus naar de gecombineerde `cron.log`.

### Productie `.env`

Controleer dat productie `.env` `APP_ENV=production` heeft staan (niet `local`). `.env.example` in de repo gebruikt al `production` als default.

### Coverage / verificatie

Bash-scripts zijn niet via Pest gedekt. De runtime-verificatie verloopt via:

1. De maandelijkse hersteltest (cron-regel 2) — exitcode 0 = restore werkt; >0 = pager.
2. Het dagelijkse cron-log (`/home/madmin/aimtrack-backups/logs/cron.log`) — niet-lege regels = success.
3. Visuele check `ls -lh /home/madmin/aimtrack-backups/db/` — verwacht 7 daily + tot 4 weekly + tot 3 monthly.

Laatst geslaagde hersteltest: **2026-05-14** → 22 tabellen · 1 user · 7 sessies · 142 schoten · 3 wapens.

## Dev-backup runner (`docker/backup/run-backup.sh`)

Voor lokale dev draait er een aparte runner-container die `scripts/backup-dev-db.sh` aanroept. Het mount-pad in de runner is `/var/www/html/scripts/backup-dev-db.sh` (gelijk aan de andere services in `docker/compose.dev.yml`).

> **Let op:** wanneer `scripts/backup-dev-db.sh` op de host als `app_aimtrack` (of een andere niet-`madmin`/root) eigenaar staat, moet de runner via `sudo` worden gestart — anders kan de container het script niet executable lezen. Zet desnoods `chmod 755 scripts/backup-dev-db.sh` voor brede leesbaarheid.

## Offsite backup

Offsite-replicatie naar Backblaze B2 (rclone install + bucket + appkey + `rclone sync` append) is afgesplitst naar follow-up issue [#84](https://github.com/Marcvdc/AimTrack/issues/84).

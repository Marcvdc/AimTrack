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

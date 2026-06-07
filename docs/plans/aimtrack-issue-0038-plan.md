# AimTrack Issue 0038 – Backup Workflow Plan
Status: APPROVED (2026-01-25)
Auteur: Cascade AI
Datum: 2026-01-25
Issue: GH-38 — "Backups voorkomen dataverlies"

## 1. Context & Aanleiding
- Staging- en productieomgevingen draaien PostgreSQL binnen Docker composities zonder ingebouwde backupstrategie.
- Huidige `scripts/backup-dev-db.sh` voorziet in lokale dumps, maar is niet aangesloten op cron/volume-setup binnen remote composities.
- Organisatie wil dataderving voorkomen door geautomatiseerde back-ups met retentie en duidelijke runbooks.

## 2. Doelen
1. Creëer herhaalbaar backupmechanisme voor staging en production dat in containers draait.
2. Zorg voor retentie/cleanup zodat schijfruimte beperkt blijft en off-host verplaatsing mogelijk blijft.
3. Documenteer runbook + configuratie zodat DevOps-team het kan beheren en auditen.

## 3. Scope & Grenzen
**In scope**
- Aanpassingen aan `docker/compose.staging.yml` en `docker/compose.production.yml` (of equivalente bestanden) om backupcontainers/cron toe te voegen.
- Re-usable script/config (bijv. parametriseren van `backup-dev-db.sh` of nieuwe variant) voor niet-dev omgevingen.
- Logging, retentie en volume mounts nodig voor automatische backupjob.
- Documentatie van installatie/operationele stappen in relevante `docs/` secties.

**Out of scope**
- Volledige offsite replicatie (S3, rsync) buiten basis dump + opschoning.
- Migratie naar managed database services.
- Realtime replication/failover (enkel batch back-ups).

## 4. Constraints & Assumpties
- Bestaande constraints blijven gelden (C-PII-Logging, C-API-Timeouts).
- Backups mogen geen secrets lekken; gebruik `.env` placeholders en zorg dat wachtwoorden via env variabelen komen.
- Cronjobs moeten idempotent draaien: geen overlappende runs (gebruik `flock` of container scheduling).
- Retentie ≥7 dagen tenzij anders afgesproken.

## 5. Aanpak (Stappen)
1. **Analyse Compose Files**
   - Inventariseer staging/prod compose bestanden, check bestaande volumes/services.
   - Bepaal beste plek voor backupjob (dedicated utility container vs. host cron + `docker exec`).
2. **Ontwerp Backup Service**
   - Parametriseer script (env-vars) voor omgevingsspecifieke credentials en paden.
   - Definieer cron schedule (bijv. dagelijks 03:00) en volume mounts (`/app`, `/backups`).
3. **Implementatie**
   - Update compose-bestanden met backup-service/cron, volume mounts, en secrets.
   - Voeg eventuele nieuwe env-variabelen toe aan `.env.example` + document constraints.
4. **Validatie & Documentatie**
   - Schrijf/aanpassen tests of scripts indien nodig (dry-run, `docker compose run backup`).
   - Documenteer runbook (starten, monitoren, loglocaties) in `docs/AimTrack/tech/` en update `docs/AimTrack/index.md` Recent.

## 6. Acceptatiecriteria
1. Staging- en productiecompose bevatten een geconfigureerde backupjob die minimaal dagelijks een gzipte dump + retentie uitvoert zonder handmatige stappen.
2. Backupconfig is gedocumenteerd (runbook + env vars) en logt naar een gedeeld volume zodat operators status kunnen verifiëren.
3. Nieuwe/gewijzigde scripts draaien succesvol in CI/local test (bijv. `docker compose run backup --dry-run`) en voldoen aan lint/test vereisten.

## 7. Afhankelijkheden & Open Vragen
- Welke exacte compose-bestandsnamen voor staging/prod? (te bevestigen)
- Opslaglocatie voor langdurige retentie (blijft voorlopig op host?).
- Is encryptie vereist voor dumpbestanden?

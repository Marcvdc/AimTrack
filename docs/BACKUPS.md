# Backups AimTrack

Productie draait op een eigen VM in de homelab, met de stack uit `docker/compose.prod.yml`. De backup bestaat uit twee lagen:

1. **De nachtelijke databasedump.** De dienst `backup` in de prod-compose maakt elke nacht een `pg_dump` van de database en bewaart die op een volume naast de database. Dat deel staat in deze repo en is hieronder beschreven.
2. **De offsite-kopie.** De VM, met de volumes van de stack, valt onder de versleutelde offsite-backup van de homelab. Hoe die loopt en hoe je de hersteltest vanaf die kopie doet, staat in het runbook `docs/runbooks/aimtrack.md` in het (private) repo `Marcvdc/homelab`. Hostnamen, paden op de VM en wachtwoorden staan alleen daar.

Tot de verhuizing naar de VM draaide productie op een Raspberry Pi, met eigen scripts voor dump, rotatie en een sync naar Backblaze B2. Die opzet bestaat niet meer, en de scripts zijn uit de repo gehaald (#190).

## De `backup`-dienst

De dienst gebruikt dezelfde image als de app. In het kort:

- `docker/backup/entrypoint.sh` schrijft een cron-job naar `/etc/cron.d/aimtrack-backup` en start Debian-`cron` op de voorgrond. Cron geeft de omgeving van de container niet door, dus de entrypoint zet de backup- en DB-variabelen boven in dat cron-bestand.
- `docker/backup/run-backup.sh` roept `scripts/backup-dev-db.sh` aan in `direct` mode. Dat is een gewone `pg_dump` tegen de `db`-dienst; de naam "dev" is historisch.
- De client is `pg_dump` 16 uit de PGDG-repository (#176). Die moet dezelfde major hebben als de database (`postgres:16`). Gaat de database naar een nieuwe major, dan gaat de client in de `Dockerfile` mee.

| Wat | Standaard in `docker/compose.prod.yml` | Instelbaar via `.env` |
|---|---|---|
| Schema | elke nacht om 03:00 (tijd van de container) | `BACKUP_SCHEDULE` |
| Bewaartermijn | 14 dagen | `BACKUP_RETENTION_DAYS` |
| Bestandsnaam | `prod-db-JJJJMMDD-UUMMSS.sql.gz` | `BACKUP_PREFIX_PROD` |
| Locatie | `/backups` in de container, volume `db_backups_prod` | |
| Log | `/backups/cron.log` | |

De dump is platte SQL, gezipt, gemaakt met `--no-owner --no-privileges`. Hij bevat alleen de database. De uploads in `storage` (volume `app_storage`) en de `.env` zitten er niet in; die komen alleen terug via de offsite-kopie van de VM.

De retentie ruimt alleen `*.sql.gz` op. Mislukt een dump, dan blijft er een los `.sql`-bestand (leeg of half gevuld) staan dat nooit vanzelf verdwijnt. Dat is meteen het teken dat er iets mis is.

De dumps staan onversleuteld op het volume, op dezelfde VM als de database. Ze beschermen tegen een fout in de applicatie of een verkeerde migratie, niet tegen het verlies van de VM. Daarvoor is de offsite-kopie.

## Controleren of de dump werkt

De commando's hieronder draai je in bash vanuit de map met de stack. `C` is een korte functie voor de compose-aanroep; zet die eerst in je shell:

```bash
C() { docker compose -f docker/compose.prod.yml --env-file .env "$@"; }
```

Op de VM is de aanroep iets anders (een eigen compose-bestand en een extra env-bestand); pas de functie daarop aan, zie het runbook.

```bash
# Welke dumps er zijn en wat de laatste runs meldden
C exec backup sh -c 'ls -l /backups; tail -n 20 /backups/cron.log'

# Is de nieuwste dump compleet? Moet 1 geven.
C exec backup sh -c 'zcat "$(ls -t /backups/*.sql.gz | head -n 1)" | tail -n 8 | grep -c "dump complete"'
```

Een bestand alleen zegt niets, kijk naar de eindregel. Sinds `pg_dump` 16.10 eindigt een dump op een regel `\unrestrict <token>` en staat `PostgreSQL database dump complete` een paar regels daarboven. Vandaar `tail -n 8` en niet `tail -n 3`.

Een dump buiten het schema om, bijvoorbeeld vlak voor een riskante migratie:

```bash
C exec backup /usr/local/bin/run-backup
```

## Een dump terugzetten

Zet een dump altijd eerst terug in een **nieuwe** database naast de bestaande, controleer die, en wissel pas daarna om. Een restore zonder foutmelding is nog geen restore die klopt.

```bash
set -o pipefail
DUMP=prod-db-JJJJMMDD-UUMMSS.sql.gz   # kies de dump uit de lijst hierboven

# 1. Lege database naast de bestaande
C exec -T db sh -c 'createdb -U "$POSTGRES_USER" -O "$DB_USERNAME" aimtrack_restore'

# 2. De dump erin, in één transactie, stoppen bij de eerste fout
C exec -T backup cat "/backups/${DUMP}" | gunzip -c \
  | C exec -T db sh -c 'psql -U "$DB_USERNAME" -d aimtrack_restore -v ON_ERROR_STOP=1 --single-transaction --quiet -o /dev/null'
```

De database maak je aan als de beheerrol van de container (`POSTGRES_USER`), de inhoud zet je terug als de rol van de app (`DB_USERNAME`). Dan is de app eigenaar van alles wat de dump aanmaakt, want de dump zelf zet geen eigenaren (`--no-owner`). In `docker/compose.prod.yml` zijn die twee dezelfde rol, en krijgt de `db`-dienst de `.env` mee, zodat `DB_USERNAME` in die container bestaat. Op de VM is de app-rol bewust geen superuser en staat die variabele niet in de `db`-container; gebruik daar de rolnamen uit het runbook. Zet je daar terug als de beheerrol, dan wordt die eigenaar van alle tabellen en loopt de app bij de eerste query tegen `permission denied`. `-o /dev/null` houdt de uitvoer van de `setval`-regels uit de dump stil; fouten komen gewoon op het scherm.

`pipefail` en `--single-transaction` zijn niet optioneel. Zonder `pipefail` telt alleen de exitcode van `psql`: ontbreekt de dump of breekt `gunzip` af, dan krijgt `psql` een lege of halve invoer en meldt het gewoon succes. Met `--single-transaction` en `ON_ERROR_STOP` blijft er bij een fout niets half staan.

Controleer daarna de inhoud, bijvoorbeeld het aantal rijen in de kerntabellen, tegen wat je verwacht of tegen de live database:

```bash
C exec -T db sh -c 'psql -U "$POSTGRES_USER" -d aimtrack_restore -c "
  SELECT (SELECT count(*) FROM users) AS users,
         (SELECT count(*) FROM sessions) AS sessions,
         (SELECT count(*) FROM session_shots) AS session_shots,
         (SELECT count(*) FROM weapons) AS weapons;"'
```

Klopt dat, wissel dan om. Zet eerst alles stil wat met de database praat, want een database met open verbindingen kun je niet hernoemen:

```bash
C stop app queue backup
C exec -T db sh -c 'psql -U "$POSTGRES_USER" -d postgres -v ON_ERROR_STOP=1 \
  -c "ALTER DATABASE aimtrack RENAME TO aimtrack_oud" \
  -c "ALTER DATABASE aimtrack_restore RENAME TO aimtrack"'
C up -d
```

Gebruik de naam uit `DB_DATABASE` als die niet `aimtrack` is. Laat `aimtrack_oud` staan tot vaststaat dat de herstelde database goed is; dat is de weg terug. Controleer daarna `/health`, een login en een sessieoverzicht.

Alles tussen het moment van de dump en het herstel is weg. Bij een dagelijkse dump is dat in het slechtste geval een dag invoer. Overleg daarom met de eigenaar voordat je omwisselt.

Het hele pad bij een mislukte release, inclusief het terugrollen van de code, staat in `docs/operations.md` onder *Als de database ook terug moet*.

## Wat niet in deze repo staat

- **De offsite-kopie en de hersteltest daarvan.** Homelab-werk, zie het runbook `docs/runbooks/aimtrack.md` in `Marcvdc/homelab`.
- **RPO en RTO.** Die horen bij de offsite-backup en worden in de homelab vastgelegd (#142).
- **Uploads en `.env`.** Geen aparte backup in deze stack; ze komen terug via de offsite-kopie van de VM.

## Lokale ontwikkeling

`scripts/backup-dev-db.sh` werkt ook los op de dev-stack. Zonder `BACKUP_MODE` gebruikt het `docker compose exec` op de `db`-dienst uit `docker/compose.dev.yml` en schrijft het naar `backups/` in de repo, met een bewaartermijn van 7 dagen. Met `BACKUP_DRY_RUN=1` maakt het alleen een placeholder, zonder `pg_dump`. Meer over de opbouw van de dienst en de variabelen staat in `docs/AimTrack/tech/backups.md`.

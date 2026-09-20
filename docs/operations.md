# Operations

Runbook voor dagelijks beheer, incidentrespons en onderhoud van AimTrack.

## Dagelijkse checks
- **Health**: monitor `/health` endpoint (verwacht 200 + JSON). Controleer logs op errors.
- **Queue**: zorg dat `queue:work` draait (container `queue`). Check `queue:failed` voor mislukte jobs, met name AI-jobs.
- **Storage**: voldoende schijfruimte voor uploads; controleer schrijfpermissies op `storage/`.

## Incidentrespons
1. **App down**: controleer containers (`docker compose ps`), bekijk logs van `web` en `app`. Herstart met `docker compose restart app web`.
2. **DB issues**: check `db` logs. Indien migrations ontbreken (bijv. Pennant `features`-tabel mist en logs tonen `Pennant features table ontbreekt; val terug op env default.`), voer de migraties uit binnen de containerstack: `docker compose -p aimtrack_dev exec app php artisan migrate --force` (met backup!).
3. **Queue stilgevallen**: herstart `queue` service; inspecteer `queue:failed` en gebruik `queue:retry` na het oplossen van de oorzaak.
4. **AI-fouten**: controleer `.env` voor API-key/base_url, timeouts in `config/ai.php`, en foutmeldingen in `ai_reflections`/`ai_weapon_insights`.
5. **Exports mislukken**: controleer bestandssysteem en permissies; valideer dat filters geldige data leveren.

## Deploy flow (productie)
1. Bouw productie-image (multi-stage) met Composer install zonder dev.
2. Draai database migraties: `php artisan migrate --force`.
3. Cache config/routes/views: `php artisan optimize`.
4. Zorg dat queue-worker opnieuw start na deploy (supervisor/systemd of compose restart).
5. Vul `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, database- en AI-credentials in.

De job `deploy-production` in `.github/workflows/ci.yml` doet dit op de Pi via `scripts/remote_deploy.sh`. Loopt dat mis, zie de rollbackprocedure hieronder.

## Rollback van een deploy

### Wat er wordt bijgehouden

`scripts/remote_deploy.sh` houdt de deploystand bij in `${DEPLOY_PATH}/.deploy/` (op productie `/opt/aimtrack/.deploy/`):

| Bestand | Inhoud |
|---|---|
| `last_successful_tag` | de image-tag die nu live staat |
| `previous_successful_tag` | de tag daarvoor, het doel van een kale rollback |
| `deploy_history` | een regel per deploy en per rollback, met tijdstip en reden |
| `registry_image` en `compose_file` | zodat een rollback op de host geen losse variabelen nodig heeft |

Die laatste twee worden pas weggeschreven nadat een deploy geslaagd is. Ze staan er dus nooit omdat een mislukte of verkeerd aangeroepen deploy ze heeft neergezet, en `scripts/rollback.sh` kan erop leunen. `scripts/remote_deploy.sh` heeft om dezelfde reden geen default voor `COMPOSE_FILE`: die moet je expliciet meegeven, ook bij een handmatige `ROLLBACK_TO=<tag> bash scripts/remote_deploy.sh`. Zonder die variabele stopt het script met exitcode 1 zonder de stack aan te raken, in plaats van op de stagingdefinitie terug te vallen.

De tags zijn de korte commit-sha's waarmee de images in GHCR staan (`ghcr.io/marcvdc/aimtrack:<short_sha>`).

### Automatisch, tijdens de deploy

Faalt de migratie of de healthcheck nadat de nieuwe containers zijn gestart, dan start het script de stack opnieuw op `last_successful_tag`, draait de healthcheck nog een keer en stopt met een eigen exitcode. De workflow wordt dus rood terwijl de app weer draait.

| Exitcode | Betekenis |
|---|---|
| 0 | deploy of rollback geslaagd |
| 1 | configuratiefout, de stack is niet aangeraakt |
| 20 | deploy mislukt, teruggerold naar de vorige tag |
| 21 | deploy mislukt en de rollback ook, de host heeft handwerk nodig |

Zet `AUTO_ROLLBACK=false` als je een kapotte versie bewust wilt laten staan om 'm te kunnen onderzoeken. Is er nog geen vorige tag vastgelegd (de allereerste deploy), dan kan het script niets terugzetten en meldt het dat expliciet.

#### Exit 20 betekent niet in alle gevallen hetzelfde

Kijk in het joblog waaróm er is teruggerold, want hoeveel een groene healthcheck na de rollback waard is, verschilt per oorzaak.

**Teruggerold na een falende healthcheck** (`the healthcheck against ... failed` in het log). De migraties waren geslaagd, dus het schema is compleet bijgewerkt en de teruggezette code draait op een schema dat netjes vooruit staat. Komt `/health` na de rollback groen terug, dan is de app hersteld en kun je in je eigen tempo naar de oorzaak kijken.

**Teruggerold na een falende migratie** (`database migrations failed` in het log). Hier bewijst een groene `/health` niets. Dat endpoint doet één query en zegt niets over de vraag of het schema nog bij de teruggezette code past. Laravel wikkelt op PostgreSQL elk migratiebestand in een eigen transactie, dus het bestand dat faalde is teruggedraaid, maar de bestanden die daarvóór in dezelfde batch slaagden zijn wél toegepast. Je staat dan op oude code met een half vooruitgeschoven schema, en dat moet je met de hand nakijken:

```bash
cd /opt/aimtrack

# 1. Welke migraties staan als toegepast geregistreerd en welke niet
docker compose -f docker/compose.prod.yml --env-file .env exec -T app \
  php artisan migrate:status

# 2. Wat zat er in de laatste batch, dus wat is er van de mislukte release blijven staan
docker compose -f docker/compose.prod.yml --env-file .env exec -T db \
  sh -c 'psql -U "${POSTGRES_USER}" -d "${POSTGRES_DB}" -c "select batch, migration from migrations order by id desc limit 10;"'

# 3. De fout zelf, naast het joblog van de workflow
docker compose -f docker/compose.prod.yml --env-file .env logs --tail=200 app
```

Vergelijk de `Ran`-regels uit stap 1 met de migraties die in de mislukte release zaten (`git show <tag> --stat -- database/migrations`). Staat daar een toegepaste migratie bij, dan loopt de teruggezette code op een schema dat 'm voor is. Is die wijziging additief, dus een nieuwe kolom of tabel, dan is dat ongevaarlijk en kan de release na een fix opnieuw. Is er iets hernoemd, verwijderd of van type veranderd, dan is de oude code kapot op precies die plek terwijl `/health` groen blijft. Loop dan handmatig het functionele pad na dat erbij hoort (login, het sessieoverzicht, de exports) en ga zo nodig verder met "Als de database ook terug moet" hieronder. Meld in beide gevallen in het incidentverslag welke migraties zijn blijven staan, want de volgende deploy begint op dat schema.

### Handmatig, achteraf

Op de productie-Pi, als de deploy-gebruiker:

```bash
cd /opt/aimtrack

# 1. Kijk wat er live staat, wat de vorige tag was en welke images lokaal aanwezig zijn
bash scripts/rollback.sh --list

# 2a. Terug naar de vorige geslaagde tag
bash scripts/rollback.sh

# 2b. Of terug naar een specifieke tag uit de historie
bash scripts/rollback.sh 4f21ab9

# 3. Controleer het resultaat
curl -fsS https://aimtrack.nl/health
docker compose -f docker/compose.prod.yml --env-file .env ps
```

Staat de gewenste tag niet meer lokaal op de host, dan haalt stap 2 'm uit GHCR, en dat vereist een geldige `docker login ghcr.io` op de host. Reken er niet op dat die er is: de deploy logt in met de token van de workflow, en die token is verlopen zodra de job klaar is. Wat er in `~/.docker/config.json` achterblijft is een dode credential.

Dat een rollback vlak na een deploy in de praktijk toch gewoon werkt, komt door iets anders: het vorige image staat dan nog lokaal op de host. `start_stack` laat bij een rollback een mislukte pull bewust passeren (`Pull failed, falling back to the image already present on this host`) en start de stack op dat lokale image. Controleer daarom eerst met `bash scripts/rollback.sh --list` of de tag die je wilt lokaal aanwezig is. Is hij dat niet, bijvoorbeeld na een `docker image prune`, dan moet je zelf inloggen met een persoonlijke token met `read:packages` voordat de pull kan slagen:

```bash
echo '<pat>' | docker login ghcr.io -u <github-gebruiker> --password-stdin
```

### Wat een rollback wel en niet terugzet

Wel: de applicatiecontainers (`app`, `queue`, `backup`) draaien weer op het oude image, inclusief de oude applicatiecode in het `app_code`-volume.

Niet:

- **Het databaseschema.** Migraties draaien vooruit en worden bij een rollback niet teruggedraaid. Daarom geldt de regel hieronder over additieve migraties.
- **De `.env` op de host.** Die wordt bij elke deploy overschreven vanuit het secret `ENV_FILE_B64`.
- **De compose-bestanden en de scripts in `docker/` en `scripts/`.** Die worden bij elke deploy met rsync overschreven. Wil je ook die terug, deploy dan de oude commit opnieuw (push die commit naar `main`, of draai de workflow op die sha).

### Migraties: alleen additief

Omdat een rollback het schema laat staan, moet oude applicatiecode kunnen blijven werken op een nieuwer schema. Houd daarom aan: kolommen en tabellen toevoegen mag, kolommen hernoemen, verwijderen of van type veranderen niet in dezelfde release als de code die erop leunt. Splits zo'n wijziging over twee releases (eerst toevoegen en dubbel schrijven, pas in een latere release opruimen).

Dit is nu een afspraak, geen geautomatiseerde controle. Ook draaien de migraties nog ná het opstarten van de nieuwe containers, dus er is een kort venster waarin de nieuwe code tegen het oude schema praat.

### Als de database ook terug moet

Een rollback van de code is niet genoeg wanneer een migratie of een bug data heeft beschadigd. Dat pad loopt via de backups (zie `docs/BACKUPS.md`):

1. Zet de stack stil zodat er niets meer bijkomt: `docker compose -f docker/compose.prod.yml --env-file .env stop app queue`.
2. Kies de dump: de dagelijkse dumps staan als `daily-*.sql.gz` in `/home/madmin/aimtrack-backups/db`.
3. Herstel naar een **tijdelijke** database en controleer die eerst: `bash /home/madmin/restore-test-aimtrack.sh --keep` herstelt de nieuwste dump naar `aimtrack_restore_test` en rapporteert tabel- en rijcounts.
4. Pas als die telling klopt, herstel je over de echte database heen. Maak daarvóór altijd een verse dump van de huidige (beschadigde) staat, anders is de situatie van vóór het herstel onherroepelijk weg.
5. Rol de code terug naar de tag die bij die dump hoort (zie hierboven) en start de stack weer: `docker compose -f docker/compose.prod.yml --env-file .env up -d`.
6. Controleer `/health` en een handmatige login plus een sessieoverzicht.

Alles tussen het moment van de dump en het herstel is weg. Bij een dagelijkse dump is dat in het slechtste geval een dag invoer, dus overleg voor stap 4 met de eigenaar.

## Backups & herstel
- Zie `docs/BACKUPS.md` voor gedetailleerde stappen (pg_dump + storage sync + restore flow).
- Test herstel periodiek: herstel DB-dump in een aparte omgeving en valideer login + sessies/weapons.

## Monitoring & logging
- Stuur logs naar stdout/Sentry. Configureer alerts op 5xx rate, queue-failures en AI-error spikes.
- Overweeg database monitoring (pg_stat_activity) en storage usage alerts.

## Beveiliging & compliance
- Patch base images regelmatig en rebuild. Houd PHP/Laravel security releases bij.
- Beperk netwerktoegang tot DB; gebruik sterke DB-wachtwoorden en separate gebruikers per omgeving.
- Secrets uitsluitend via `.env`/secret store; nooit committen.
- Schakel HTTPS/secure cookies in productie; configureer trusted proxies.

## Rapportage
- Exporteer CSV/PDF bij verzoeken. Voeg handmatig auditnotities toe indien juridisch vereist.

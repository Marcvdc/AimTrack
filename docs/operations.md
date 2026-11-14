# Operations

Runbook voor dagelijks beheer, incidentrespons en onderhoud van AimTrack.

## Dagelijkse checks
- **Health**: monitor `/health` endpoint (verwacht 200 + JSON). Controleer logs op errors.
- **Queue**: zorg dat `queue:work` draait (container `queue`). Check `queue:failed` voor mislukte jobs, met name AI-jobs.
- **Storage**: voldoende schijfruimte voor uploads; controleer schrijfpermissies op `storage/`.

## Incidentrespons
1. **App down**: controleer containers (`docker compose ps`), bekijk logs van `web` en `app`. Herstart met `docker compose restart app web`.
2. **DB issues**: check `db` logs. Indien migrations ontbreken (bijv. Pennant `features`-tabel mist en logs tonen `Pennant features table ontbreekt; val terug op env default.`), voer de migraties uit binnen de containerstack: `docker compose -p aimtrack_dev exec app php artisan migrate --force` (met backup!).
3. **Queue stilgevallen** (`/health` meldt `queue_stalled`, zie hieronder): herstart `queue` service; inspecteer `queue:failed` en gebruik `queue:retry` na het oplossen van de oorzaak.
4. **AI-fouten**: controleer `.env` voor API-key/base_url, timeouts in `config/ai.php`, en foutmeldingen in `ai_reflections`/`ai_weapon_insights`.
5. **Exports mislukken**: controleer bestandssysteem en permissies; valideer dat filters geldige data leveren.

### Betekenis van `queue_stalled`

`/health` en `/api/health` geven 503 met `checks.queue.error: queue_stalled` als de oudste job in de `jobs`-tabel die al uitgevoerd had moeten worden langer wacht dan `HEALTH_QUEUE_MAX_AGE_SECONDS` (standaard 300, zie `config/health.php`). Jobs met een delay tellen niet mee, jobs die een worker nu verwerkt ook niet, een reservering ouder dan `retry_after` wel. De leeftijd staat niet in de response (het endpoint is publiek) maar in de applicatielog, als warning `Queue stilgevallen` met `age_seconds`. Elke aanroep van `/health` schrijft bij stilstand zo'n warning, dus bij een monitor die elke minuut pollt komt er elke minuut een regel bij; dat is bewust, de log blijft zo gelijk aan wat de monitor ziet. Alle queue-namen tellen mee. Bijna altijd betekent het dat er geen worker draait of dat die vastzit.

Wat je doet:
1. Kijk of de worker draait: `docker compose ps queue` en `docker compose logs --tail=100 queue`.
2. Herstart hem: `docker compose restart queue`. De wachtende jobs blijven in de tabel staan en worden daarna alsnog verwerkt.
3. Blijft de melding staan, kijk dan naar `php artisan queue:failed` en naar een enkele langlopende job die de worker blokkeert. Verhoog de drempel alleen als er legitiem lange jobs zijn.

De drempel staat standaard op 300 seconden. Een grote burst AI-jobs kan de wachttijd tijdelijk boven die grens brengen en dan vals alarm geven bij externe monitoring; zet de drempel in dat geval hoger. De config wordt bij het opstarten van de container gecachet (`config:cache` in `docker/entrypoint.sh`), dus een gewijzigde `HEALTH_QUEUE_MAX_AGE_SECONDS` werkt pas na een herstart van de container.

`queue_unavailable` is iets anders: de queue-configuratie of de `jobs`-tabel is niet bereikbaar.

## Deploy flow (productie)
1. Bouw productie-image (multi-stage) met Composer install zonder dev.
2. Draai database migraties: `php artisan migrate --force`.
3. Cache config/routes/views: `php artisan optimize`.
4. Zorg dat queue-worker opnieuw start na deploy (supervisor/systemd of compose restart).
5. Vul `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, database- en AI-credentials in.

Sinds 2026-10-12 gebeurt dit met de hand op de productie-VM in de homelab (runbook `docs/runbooks/aimtrack.md` in `Marcvdc/homelab`). De job `deploy-production` die dit via `scripts/remote_deploy.sh` op de Pi deed, is uitgezet; zie `docs/plans/deploy-pipeline-uit-plan.md`. Terug naar een vorige stand gaat daar met een snapshot van de VM van vóór de update, of door de vorige commit opnieuw te bouwen. De rollbackprocedure hieronder hoort bij `scripts/remote_deploy.sh` en geldt pas weer als er een pipeline komt die dat script aanroept.

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
- **De `.env` op de host.** Die werd bij elke deploy overschreven vanuit het secret `ENV_FILE_B64`; dat secret verdwijnt met het opruimen van de GitHub-environments (zie het plan).
- **De compose-bestanden en de scripts in `docker/` en `scripts/`.** Die werden bij elke deploy met rsync overschreven. Er is nu geen workflow meer die dat doet; terug naar een oude stand gaat op de host zelf, zie *Deploy flow (productie)* hierboven.

### Migraties: alleen additief

Omdat een rollback het schema laat staan, moet oude applicatiecode kunnen blijven werken op een nieuwer schema. Houd daarom aan: kolommen en tabellen toevoegen mag, kolommen hernoemen, verwijderen of van type veranderen niet in dezelfde release als de code die erop leunt. Splits zo'n wijziging over twee releases (eerst toevoegen en dubbel schrijven, pas in een latere release opruimen).

Dit is nu een afspraak, geen geautomatiseerde controle. Ook draaien de migraties nog ná het opstarten van de nieuwe containers, dus er is een kort venster waarin de nieuwe code tegen het oude schema praat.

### Als de database ook terug moet

Een rollback van de code is niet genoeg wanneer een migratie of een bug data heeft beschadigd. Dat pad loopt via de backups (zie `docs/BACKUPS.md`):

1. Zet de stack stil zodat er niets meer bijkomt: `docker compose -f docker/compose.prod.yml --env-file .env stop app queue`. De `backup`-dienst blijft draaien, die heb je nodig om bij de dumps te komen.
2. Kies de dump: de `backup`-dienst zet elke nacht om 03:00 een `prod-db-JJJJMMDD-UUMMSS.sql.gz` in `/backups` (volume `db_backups_prod`) en bewaart die 14 dagen. `docker compose -f docker/compose.prod.yml --env-file .env exec backup ls -l /backups` toont wat er is. Controleer dat de gekozen dump compleet is, zie *Controleren of de dump werkt* in `docs/BACKUPS.md`.
3. Herstel naar een **tijdelijke** database `aimtrack_restore` naast de bestaande en controleer die eerst. De commando's staan in `docs/BACKUPS.md` onder *Een dump terugzetten*.
4. Pas als die controle klopt, wissel je om. Maak daarvóór altijd een verse dump van de huidige (beschadigde) staat met `docker compose -f docker/compose.prod.yml --env-file .env exec backup /usr/local/bin/run-backup`, anders is de situatie van vóór het herstel onherroepelijk weg. Het omwisselen (hernoemen naar `aimtrack_oud`, de herstelde database op de plek van `aimtrack`) staat ook in `docs/BACKUPS.md`; sla de `up -d` aan het eind daarvan hier over, die komt in stap 5.
5. Rol de code terug naar de tag die bij die dump hoort (zie hierboven) en start de stack weer: `docker compose -f docker/compose.prod.yml --env-file .env up -d`.
6. Controleer `/health` en een handmatige login plus een sessieoverzicht.

Alles tussen het moment van de dump en het herstel is weg. Bij een dagelijkse dump is dat in het slechtste geval een dag invoer, dus overleg voor stap 4 met de eigenaar.

## Backups & herstel
- Zie `docs/BACKUPS.md`: de nachtelijke dump van de `backup`-dienst, controleren en terugzetten.
- De offsite-kopie van de VM en de hersteltest daarvan staan in het runbook `docs/runbooks/aimtrack.md` in `Marcvdc/homelab`.

## Monitoring & logging
- Logs gaan naar het daily-bestand en stdout/stderr (`LOG_STACK`). Configureer alerts op 5xx rate, queue-failures en AI-error spikes.
- Overweeg database monitoring (pg_stat_activity) en storage usage alerts.

### Sentry

Elke gerapporteerde exception gaat via `Integration::handles()` in `withExceptions()` van `bootstrap/app.php` naar Sentry: onbehandelde fouten uit web-requests en commands, elke mislukte poging van een queue-job, en `report()`-aanroepen in de code. Een job die met `$this->fail()` stopt komt binnen via de `Queue::failing`-hook in `AppServiceProvider`, die `report()` aanroept. `dontReportDuplicates()` in dezelfde callback zorgt dat een job die op zijn laatste poging gooit maar één keer binnenkomt. Een job met drie pogingen die drie keer faalt geeft dus drie events in hetzelfde Sentry-issue.

Het logkanaal `sentry` hoort niet in `LOG_STACK`: dan kwam elke exception twee keer binnen, en gewone foutlogs (ook die van de feature flags) zouden in Sentry belanden. `config/logging.php` haalt het kanaal er daarom uit, ook als het in de `.env` staat.

Env-variabelen voor productie:

| Variabele | Nodig | Waarde |
|---|---|---|
| `SENTRY_LARAVEL_DSN` | ja, anders gaat er niets naar Sentry | DSN uit het Sentry-project; alleen in de `.env` op de host, nooit in de repo |
| `SENTRY_ENVIRONMENT` | nee | standaard `APP_ENV`, dus `production` |
| `SENTRY_RELEASE` | nee | de deploy-tag, zodat fouten aan een release hangen |
| `SENTRY_TRACES_SAMPLE_RATE` | nee | `0.0` (geen performance-tracing) |
| `SENTRY_PROFILES_SAMPLE_RATE` | nee | `0.0` |

Zonder `SENTRY_LARAVEL_DSN` verandert er niets: de client heeft geen adres en slaat elk event over. Tests zetten de DSN in `phpunit.xml` geforceerd op leeg.

Wat `config/sentry.php` tegenhoudt:

- `send_default_pii` staat op `false`: geen IP-adres, geen cookies, en de headers `Authorization`, `Cookie`, `X-Forwarded-For` en `X-Real-IP` worden gefilterd.
- `max_request_body_size` staat op `never`: de request-body (formuliervelden, JSON) gaat niet mee. Dat staat los van `send_default_pii`.
- `before_send` wijst naar `App\Support\Sentry\ScrubRequestData`: die haalt de query string uit `request.query_string`, `request.url` en de `Referer`-header, en gooit een eventuele body weg. sentry-php neemt de query string anders altijd mee, ook met `send_default_pii` uit, en een resetlink met `?email=` zou dan een e-mailadres sturen.

Wat wel meegaat: de exception-klasse en -boodschap, de stacktrace met broncoderegels, het URL-pad, de methode, de overige headers (zoals `User-Agent`) en de logregels van vóór de fout als breadcrumbs. Zet dus geen e-mailadressen of andere persoonsgegevens in exception-boodschappen of logregels.

## Beveiliging & compliance
- Patch base images regelmatig en rebuild. Houd PHP/Laravel security releases bij.
- Beperk netwerktoegang tot DB; gebruik sterke DB-wachtwoorden en separate gebruikers per omgeving.
- Secrets uitsluitend via `.env`/secret store; nooit committen.
- Schakel HTTPS/secure cookies in productie; configureer trusted proxies.

## Rapportage
- Exporteer CSV/PDF bij verzoeken. Voeg handmatig auditnotities toe indien juridisch vereist.

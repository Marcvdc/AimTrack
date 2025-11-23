# Operations

Runbook voor dagelijks beheer, incidentrespons en onderhoud van AimTrack.

## Dagelijkse checks
- **Health**: monitor `/health` endpoint (verwacht 200 + JSON). Controleer logs op errors.
- **Queue**: zorg dat `queue:work` draait (container `queue`). Check `queue:failed` voor mislukte jobs, met name AI-jobs.
- **Storage**: voldoende schijfruimte voor uploads; controleer schrijfpermissies op `storage/`.

## Incidentrespons
1. **App down**: controleer containers (`docker compose ps`), bekijk logs van `web` en `app`. Herstart met `docker compose restart app web`.
2. **DB issues**: check `db` logs. Indien migrations ontbreken, voer `php artisan migrate --force` uit (met backup!).
3. **Queue stilgevallen**: herstart `queue` service; inspecteer `queue:failed` en gebruik `queue:retry` na het oplossen van de oorzaak.
4. **AI-fouten**: controleer `.env` voor API-key/base_url, timeouts in `config/ai.php`, en foutmeldingen in `ai_reflections`/`ai_weapon_insights`.
5. **Exports mislukken**: controleer bestandssysteem en permissies; valideer dat filters geldige data leveren.

## Deploy flow (productie)
1. Bouw productie-image (multi-stage) met Composer install zonder dev.
2. Draai database migraties: `php artisan migrate --force`.
3. Cache config/routes/views: `php artisan optimize`.
4. Zorg dat queue-worker opnieuw start na deploy (supervisor/systemd of compose restart).
5. Vul `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, database- en AI-credentials in.

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
- Exporteer CSV/PDF bij verzoeken (bijv. WM-4). Voeg handmatig auditnotities toe indien juridisch vereist.

# Security

Beveiligingsrichtlijnen voor AimTrack.

## Threat model
- Single-tenant app: risico’s vooral rond ongeautoriseerde toegang tot de host of database.
- Gevoelige data: schietsessies, wapeninformatie, AI-output. Geen multi-user delen.

## Basismaatregelen
- **Auth**: gebruik standaard Laravel-auth met sterke wachtwoorden en HTTPS.
- **Transport**: forceer HTTPS in productie; stel trusted proxies in.
- **Secrets**: alleen in `.env` of secretsmanager; nooit committen. Rotatie via CI/secrets store.
- **Headers**: zorg voor HSTS, X-Frame-Options, X-Content-Type-Options via webserver.
- **Rate limiting**: gebruik Laravel throttle middleware voor login/APIs indien blootgesteld.

## Data & opslag
- Alle queries filteren op `user_id`. Gebruik policies/scopes in Filament resources.
- Bestanden via Laravel storage; configureer private disks of presigned URLs voor downloads indien publiek internet.
- Backups versleutelen waar mogelijk; beperk toegang tot storage bucket.

## AI-specifiek
- Provider keuze via config; vermijd het delen van meer data dan nodig.
- Prompts bevatten veiligheidsdisclaimers; geen wapen- of veiligheidsadvies dat wetgeving schendt.
- AI-jobs loggen status en fouten; bewaak rate limits en timeouts.

## Dependencies & updates
- Draai regelmatig `composer update` voor security fixes; gebruik `composer audit` in CI.
- Base images patchen en rebuilden; controleer PHP/Laravel security advisories.

## Logging & auditing
- Gebruik Sentry/stack driver; log 5xx, failed jobs, en AI-fouten.
- Zorg dat logs geen secrets bevatten (mask keys). Bewaar retentie volgens beleid.

## Vulnerability disclosure
- Zie `SECURITY.md` voor meldingsproces. Reageer snel op gemelde issues en patch/roll-out.

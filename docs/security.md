# Security

Laatst bijgewerkt: 2026-11-14

Beveiligingsrichtlijnen voor AimTrack.

## Threat model
- Eén applicatie en één database voor alle gebruikers, met een verenigingslaag erbovenop. Risico's: ongeautoriseerde toegang tot de host of database, en een gebruiker die bij data van een ander komt.
- Gevoelige data: schietsessies, wapeninformatie, AI-output en de Claude API-keys van gebruikers en verenigingen (versleuteld opgeslagen).
- Delen binnen een vereniging: een coach of beheerder van de actieve vereniging kan de sessies van de leden van die vereniging lezen, niet wijzigen (`SessionPolicy::view`, `User::isCoachVan`, `CoachSessieResource`). `WeaponPolicy::view` geeft via dezelfde `isCoachVan` ook leesrecht op de wapens van leden. Verder ziet een verenigingsbeheerder in "Mijn vereniging" de namen en e-mailadressen van alle leden (`VerenigingBeheer`). Dit zijn de plekken waar data van de ene gebruiker voor een andere zichtbaar is. Een fout in rol, lidmaatschap of actieve vereniging geeft dus inzage in andermans sessies; houd dat in het oog bij wijzigingen aan `VerenigingService` en de policies.
- Wie `users.is_admin` heeft, ziet het gebruikersbeheer en de mislukte jobs.

## Basismaatregelen
- **Auth**: gebruik standaard Laravel-auth met sterke wachtwoorden en HTTPS.
- **Registratie**: zelf een account aanmaken staat standaard uit (`AUTH_REGISTRATION_ENABLED=false`). Aan betekent: registratie plus verplichte e-mailverificatie.
- **PHP**: de FPM-pool van de production-image schakelt shell- en procesfuncties en `allow_url_fopen` uit; de CLI niet. Zie `docs/PROD_HARDENING.md`.
- **Transport**: forceer HTTPS in productie; stel trusted proxies in.
- **Secrets**: alleen in `.env` of secretsmanager; nooit committen. Rotatie via CI/secrets store.
- **Headers**: zorg voor HSTS, X-Frame-Options, X-Content-Type-Options via webserver.
- **Rate limiting**: gebruik Laravel throttle middleware voor login/APIs indien blootgesteld.

## Data & opslag
- Sessies en wapens filteren op `user_id` van de ingelogde gebruiker, met `SessionPolicy` en `WeaponPolicy`. De uitzondering is de coach-inzage hierboven.
- Bestanden via Laravel storage; configureer private disks of presigned URLs voor downloads indien publiek internet.
- Backups versleutelen waar mogelijk; beperk toegang tot storage bucket.

## AI-specifiek
- De provider is Anthropic. De key is per gebruiker (en optioneel per vereniging) en staat versleuteld in de database, niet in `.env`; vermijd het delen van meer data dan nodig.
- Prompts bevatten veiligheidsdisclaimers; geen wapen- of veiligheidsadvies dat wetgeving schendt.
- AI-jobs loggen status en fouten; bewaak rate limits en timeouts.

## Dependencies & updates
- Draai regelmatig `composer update` voor security fixes; gebruik `composer audit` in CI.
- Base images patchen en rebuilden; controleer PHP/Laravel security advisories.

## Logging & auditing
- Logging loopt via de stack (`LOG_STACK`, met het kanaal `sentry`); mislukte queue-jobs gaan via de `Queue::failing`-hook naar het log en naar Sentry. Log failed jobs en AI-fouten.
- Zorg dat logs geen secrets bevatten (mask keys). Bewaar retentie volgens beleid.

## Vulnerability disclosure
- Zie `SECURITY.md` voor meldingsproces. Reageer snel op gemelde issues en patch/roll-out.

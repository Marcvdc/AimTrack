# Plan: klaar voor internet (afhankelijkheden, PHP-hardening op FPM, registratie achter een vlag)

**Taak**: GitHub-issue #178 "Klaar voor internet: afhankelijkheden, PHP-hardening op FPM, registratie achter een vlag"
**Status**: APPROVED (Marc, 2026-10-17: branch op #169, datamigratie)
**Classificatie**: COMPLEX (drie deelopdrachten, elf AC's, raakt Dockerfile, auth en dependencies)

**Stack**

```
Repo: Marcvdc/AimTrack
Stack: Laravel 12 / PHP 8.4 / Type A (Eloquent) / Filament 5 / Pest 4 / PostgreSQL (prod) + SQLite (tests)
Boost-MCP: niet verbonden in deze sessie (wsl.exe niet gevonden); stack afgeleid uit composer.json en de bestanden.
Worktree: ../aimtrack-issue-178, branch feature/issue-178
```

## Basis van de branch: bovenop #169

PR #169 (`claude/deps-update`) doet deelopdracht 1 al grotendeels: een volledige `composer update`
binnen de bestaande constraints, gereviewd, met de Copilot-migraties en de commonmark-bump die daarbij
nodig waren. Hij bevat `main` (`8c1058b`) volledig. Deze branch takt daarom van `origin/claude/deps-update`
(`e2bce7e`) en niet van `main`, zodat er één PR naar `main` komt die alle drie de punten dekt, zonder
het werk van #169 over te doen of een tweede `composer.lock` te laten botsen.

Gevolg: de PR voor #178 bevat de commits van #169. Na merge is #169 overbodig. Dat sluiten laat ik aan
jou, net als #126 (Dependabot).

Doelbranch is `main`. `origin/develop` staat 63 commits achter `main` en de laatste merges (#157, #176,
#177) gingen allemaal naar `main`, dus het `develop`-model uit de worktree-guideline is in de praktijk
niet in gebruik.

## 1. Afhankelijkheden

- `composer update` opnieuw draaien bovenop #169 (binnen de majors), omdat de advisory-database sinds
  2026-10-04 is doorgegaan. `composer audit` voor en na in de PR, plus `composer audit --no-dev` voor
  de runtime-kant.
- Vraagt een melding een major, dan apart benoemen in de PR en niet stilzwijgend meenemen.
- npm (`postcss`, `source-map-js`): `npm audit fix` zonder `--force` als dat de lock schoon bijwerkt
  en de build groen blijft; anders laten staan en melden.
- Volledige Pest-suite in `aimtrack-dev:local`.

## 2. PHP-hardening in de production-image

Bevinding uit de code: de webkant van de app roept zelf geen `exec`, `proc_open`, `Process` of
`pcntl_*` aan (grep over `app/`, `routes/`, `config/`, `resources/`). De vendor-paden die de webkant
raakt:

- Anthropic/`laravel/ai`, Copilot, Sentry en de Http-facade: Guzzle met `ext-curl` (zit in de officiële
  image), dus geen `allow_url_fopen` nodig. Dit verifieer ik met `php -m` en een echte HTTP-call via FPM.
- dompdf: `isRemoteEnabled` staat uit, leest alleen lokale bestanden; lokaal `file_get_contents` werkt
  zonder `allow_url_fopen`.
- Mail: Symfony Mailer SMTP gebruikt `stream_socket_client`, niet geraakt. `sendmail` zou `proc_open`
  nodig hebben, maar prod gebruikt `MAIL_MAILER=smtp`.
- Symfony Mime: `FileinfoMimeTypeGuesser` (ext-fileinfo); de `file`-binary-guesser via `passthru` valt
  alleen terug als fileinfo ontbreekt.

Aanpak:

- `docker/php/fpm-hardening.conf` (nieuw): `[www]`-pool met
  `php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen,pcntl_exec`
  en `php_admin_flag[allow_url_fopen] = off`. Komt in de `production`-stage als
  `/usr/local/etc/php-fpm.d/zz-hardening.conf`. De dev-stage en de CLI blijven ongemoeid, dus
  `queue:work` (pcntl-signalen) en de entrypoint (artisan) werken zoals nu.
- `docker/php/hardening.ini` (nieuw) met `expose_php = Off`, ook alleen in de `production`-stage. Dit
  is een globale ini, maar heeft op de CLI geen effect.
- Blijkt bij de verificatie dat de webkant toch iets uit de lijst nodig heeft, dan gaat dat uit de lijst
  en staat de reden in de PR.

Verificatie (in de PR beschreven, door mij uitgevoerd op een gebouwde image):

- `docker build --target production` en dan in een wegwerpcontainer:
  - CLI: `php -r 'var_dump(ini_get("disable_functions"), ini_get("allow_url_fopen"));'` geeft leeg en `1`.
  - FPM: `php-fpm -tt` toont de pool-waarden, en een echt FPM-request via `cgi-fcgi` (alleen in de
    wegwerpcontainer geïnstalleerd, niet in de image) op een tijdelijk scriptje geeft de beperkte waarden
    terug en `function_exists`/aanroep van `exec` faalt.
  - Response-header: geen `X-Powered-By`.
  - Queue: in dezelfde image met SQLite en `QUEUE_CONNECTION=database` een job dispatchen en
    `php artisan queue:work --once --stop-when-empty` laten verwerken.
- Een Pest-test kan dit niet raken (de suite draait op de CLI); de bewijsvoering staat daarom als
  reproduceerbare commando's in de PR en in `docs/PROD_HARDENING.md`.

## 3. Registratie achter een vlag, standaard uit

- `config/auth.php`: `'registration_enabled' => (bool) env('AUTH_REGISTRATION_ENABLED', false)`.
  Naam van de variabele voor de homelab-repo: **`AUTH_REGISTRATION_ENABLED`**.
- `AdminPanelProvider`: `->registration()` en `->emailVerification()` alleen als die config aan staat.
  Filament registreert de routes dan niet, dus `/admin/register` geeft 404 met de vlag uit.
- `User implements MustVerifyEmail`. Met de vlag uit dwingt Filament geen verificatie af, dus dat
  verandert dan niets aan het inloggen.
- Bestaande accounts: een **datamigratie** die bij elke user met `email_verified_at IS NULL` de waarde op
  `created_at` zet (of `now()` als die leeg is). Eenmalig, draait mee met de gewone `migrate` bij de
  uitrol. Zo sluit het ene productie-account zichzelf niet buiten op het moment dat de vlag ooit aan
  gaat. `down()` doet niets (we weten niet meer welke rijen we hebben aangeraakt). Gekozen boven een
  artisan-commando omdat het dan niet vergeten kan worden.
- `.env.example` krijgt `AUTH_REGISTRATION_ENABLED=false` met een regel uitleg.

Bijwerking om te benoemen in de PR, niet om op te lossen: `PromoteFirstUserToAdmin` hangt aan het
`Registered`-event. Met registratie uit maakt een verse self-hosted install via `make:filament-user`
geen beheerder. Productie heeft zijn account al; voor een verse install komt een regel in de docs.

## Raakt

- `composer.lock` (en eventueel `package-lock.json`)
- `Dockerfile` (production-stage: twee COPY-regels)
- `docker/php/fpm-hardening.conf`, `docker/php/hardening.ini` (nieuw)
- `config/auth.php`, `app/Providers/Filament/AdminPanelProvider.php`, `app/Models/User.php`
- `database/migrations/2026_10_17_xxxxxx_mark_existing_users_as_verified.php` (nieuw)
- `.env.example`, `docs/PROD_HARDENING.md`, `docs/security.md`
- Tests: `tests/Feature/Auth/RegistrationFlagTest.php`, `tests/Feature/Auth/MarkExistingUsersVerifiedMigrationTest.php` (nieuw)

## Tests

`tests/Feature/Auth/RegistrationFlagTest.php`:

- vlag uit (default): `GET /admin/register` geeft 404, en de loginpagina toont geen registratielink
- vlag aan: registratie via de Filament-`Register`-pagina maakt een user aan, verstuurt
  `VerifyEmail` (Notification::fake), en de nieuwe user wordt van `/admin` naar de verificatieprompt
  gestuurd
- vlag aan: een geverifieerde user komt gewoon op `/admin`
- vlag aan: de verificatielink (signed route) zet `email_verified_at`

Omdat de panelconfig bij het booten wordt gelezen, zet de test de config en bouwt hij het panel opnieuw
op (`Filament::getPanel('admin')` opnieuw registreren of de app herstarten met `refreshApplication`);
de precieze techniek bepaal ik bij het bouwen en staat in de PR.

`MarkExistingUsersVerifiedMigrationTest`: user zonder verificatie krijgt `created_at`, user met
verificatie blijft ongewijzigd.

Daarna de volledige suite en Pint.

## Acceptatiecriteria (uit #178)

1. composer update binnen de majors; majors apart benoemd
2. `composer audit` geen high/critical voor runtime; restant met reden in de PR
3. volledige Pest-suite groen
4. (optioneel) npm-meldingen
5. pool-config in `docker/php/` met de zeven functies en `allow_url_fopen` uit, uitzonderingen met reden
6. `expose_php = Off`
7. suite groen + reproduceerbaar bewijs FPM beperkt, CLI niet
8. queue verwerkt een job in de production-image
9. registratie aan config uit `.env`, standaard uit, naam in de PR
10. vlag aan: `emailVerification()` en `MustVerifyEmail`
11. bestaande accounts niet buitengesloten (migratie)
12. tests voor vlag uit (404) en vlag aan (registratie + verificatie)

## Buiten scope

#155 (bijlagen), uitrol op VM 202, de mailconfig, het `develop`-branchmodel.

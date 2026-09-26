# Plan: demo-schoten, Nederlandse locale, Europe/Amsterdam-weergave en één datumnotatie

**Taak**: GitHub-issue #143 — "Demo-data maakt geen schoten, en de app draait op Engels/UTC"
**Branch**: `Marcvdc/issue-143`
**Status**: APPROVED (issue-AC's zijn de goedgekeurde scope; issue-comment legt de afbakening vast)

## Stack

```
Repo: aimtrack (kjsoftware/aimtrack)
Stack: Laravel 12 / PHP 8.4 / Type A (Eloquent) / Filament v5 / Livewire v4 / Pest v4 / PostgreSQL (prod+dev), SQLite in-memory (tests)
KJ-packages: geen kjsoftware/* packages in deze repo
Boost actief: ja (AGENTS.md aanwezig); de laravel-boost MCP-server kon in deze sessie niet verbinden (ENOENT wsl.exe), dus vendor is direct gelezen in plaats van via de MCP-tools.
```

Preflight: geen Jira/Odoo-bron, de technische uitwerking staat in de issue-body zelf. Check 3 (bouwpoort
via MCP) vervalt: onbemande run. Check 1 en 2 gedraaid: alle in het issue genoemde paden en regelnummers
bestaan nog en kloppen met de huidige `main` (e995af5).

## Probleem

De demo-knop belooft een gevulde app maar maakt geen enkel `SessionShot`, waardoor de roos, de ringen en
de eindscore leeg blijven voor iedere nieuwe gebruiker. Daarnaast draait de app op de Engelse
standaardlocale (Filament-chrome en validatiefouten in het Engels) en toont dezelfde applicatie een datum
op acht verschillende manieren, met als negende de Amerikaanse Filament-default `M j, Y` bij kale
`->date()`-aanroepen. In een schietlogboek is een tijd die een uur naast de werkelijkheid ligt gewoon
foute data, dus dit blokkeert het in gebruik nemen.

## Aanpak

Type A (Eloquent), vier losse sporen in één PR omdat ze allemaal aan dezelfde "de app spreekt geen
Nederlands"-oorzaak hangen.

**Tijdzone — bewust géén `app.timezone` naar Europe/Amsterdam.** De Postgres-kolommen zijn
`timestamp without time zone` en alles wat er nu in staat is in UTC geschreven. `config('app.timezone')`
stuurt zowel het schrijven als het teruglezen aan, dus die vlag omzetten zou elke bestaande rij een of
twee uur verschuiven zonder dat er één byte in de database verandert. Opslag blijft daarom UTC;
alleen de weergave gaat naar Europe/Amsterdam via een nieuwe `app.display_timezone` plus
`FilamentTimezone::set()`. Nul dataverschuiving, en het verschil tussen opslag en weergave wordt met een
test vastgelegd.

**Locale.** `config/app.php` krijgt `nl` als code-default (de app is Nederlandstalig per scope, geen
taalwisselaar), met `en` als fallback zodat een ontbrekende sleutel leesbaar blijft. De Filament-`nl`
-vertalingen zitten al in vendor en worden dan vanzelf actief; Laravel zelf levert alleen `en` mee, dus
`lang/nl/` wordt met de hand aangelegd (validation, auth, passwords, pagination).

**Datumnotatie.** Eén `App\Support\DateFormat` met constanten en null-veilige helpers. De
Filament-defaults worden centraal gezet met `Table::configureUsing()` en `Schema::configureUsing()`, zodat
alle kale `->date()`-aanroepen meebewegen zonder dat die bestanden aangeraakt hoeven te worden. Twee
gedocumenteerde uitzonderingen: maand-granulariteit op trendassen (een maandbucket ís geen datum) en
ISO `Y-m-d` in machine-leesbare output (CSV-export, AI-tool-payloads).

**Demo-schoten.** Shot-generatie gaat naar een eigen `DemoShotGenerator` in plaats van in de al volle
seeder; die gebruikt de bestaande `ShotScoringService` (gebruiken, niet wijzigen) en is deterministisch
via een lokale LCG, zodat de demo er voor iedereen hetzelfde uitziet en tests er hard op kunnen asserten.
Bewust géén `mt_srand()`: dat zet de globale random-state van het proces om en beïnvloedt alles wat er
daarna in de testrun met Faker gebeurt.

## Raakt

### Config en env
- `config/app.php` — `timezone` naar `env('APP_TIMEZONE', 'UTC')` met een waarschuwing in het commentaar dat omzetten bestaande rijen herinterpreteert; nieuwe `display_timezone` (`env('APP_DISPLAY_TIMEZONE', 'Europe/Amsterdam')`); `locale` default `nl`, `fallback_locale` default `en`, `faker_locale` default `nl_NL`
- `.env.example` — `APP_LOCALE=nl`, `APP_FALLBACK_LOCALE=en`, `APP_FAKER_LOCALE=nl_NL`, `APP_DISPLAY_TIMEZONE=Europe/Amsterdam`; `APP_TIMEZONE=UTC` blijft
- `.env.local.example` — idem, plus `APP_TIMEZONE` van `Europe/Amsterdam` terug naar `UTC` (die regel deed tot nu toe niets en zou na deze PR wél iets doen, namelijk het verkeerde)

### Vertalingen
- `lang/nl/validation.php` — volledige Nederlandse regelset, inclusief `attributes`-blok
- `lang/nl/auth.php`, `lang/nl/passwords.php`, `lang/nl/pagination.php` — Nederlandse varianten

### Datumnotatie
- `app/Support/DateFormat.php` (nieuw) — `DATE = 'd-m-Y'`, `DATE_TIME = 'd-m-Y H:i'`, `TIME = 'H:i'`, `MONTH = 'M Y'`, `MACHINE_DATE = 'Y-m-d'`; helpers `date()`, `dateTime()`, `time()`, `month()`, `displayTimezone()`; de datetime-helpers schuiven naar de weergave-tijdzone, `date()` niet (een datumkolom heeft geen tijdzone)
- `app/Providers/AppServiceProvider.php` — `FilamentTimezone::set()`, `Table::configureUsing()` en `Schema::configureUsing()` met de drie display-formats
- `resources/views/filament/modals/ai-coach-question.blade.php:18,29` — `d/m/Y` en `d-m-Y H:i` naar de helpers
- `resources/views/filament/pages/coach-page.blade.php:144,197` — `d M` naar `DATE`
- `resources/views/filament/resources/sessions/wizard-shots-step.blade.php:20` — `d M Y` naar `DATE`
- `resources/views/filament/resources/sessions/list-sessions.blade.php:143,144` — `d M` naar `DATE`
- `resources/views/filament/resources/sessions/view-session.blade.php:23,52` — `l d F Y` naar `DATE`, `H:i` naar `TIME`
- `resources/views/filament/resources/session-resource/pages/manage-session-shots.blade.php:14` — naar `DATE`
- `resources/views/filament/resources/weapons/view-weapon.blade.php:24,97,171` — naar `DATE`; `:110,111` blijft maand-granulariteit maar via `MONTH`
- `resources/views/exports/sessions.blade.php:68` — PDF is mensleesbaar, dus `Y-m-d` naar `DATE`
- `app/Filament/Resources/UserResource.php:68`, `app/Filament/Resources/CoachSessies/Tables/CoachSessiesTable.php:25`, `app/Filament/Resources/CoachSessies/Schemas/CoachSessieInfolist.php:21`, `app/Filament/Widgets/FailedJobsWidget.php:54` — handmatige formats eruit, centrale default erin
- `app/Livewire/SessionShotBoard.php:310,444` — `H:i` naar `TIME`
- `app/Filament/Resources/WeaponResource/RelationManagers/SessionWeaponsRelationManager.php:40` — optielabel naar `DATE`
- `app/Services/Export/SessionExportService.php:79,99`, `app/Services/Ai/ShooterCoach.php:143,211`, `app/Filament/Copilot/Tools/{SessionLookupTool,ShooterContextTool,WeaponLookupTool}.php` — blijven ISO, maar met `MACHINE_DATE` plus een regel commentaar waarom
- Kale `->date()`-aanroepen in `AttachmentResource`, `SessionResource`, `WeaponResource` en `SessionWeaponsRelationManager` worden **niet** aangeraakt: die volgen vanzelf de nieuwe centrale default

### Demo-data
- `app/Services/Demo/DemoShotGenerator.php` (nieuw) — genereert per wapenregel `rounds_fired` schoten in series van 10, met een bias per `Deviation` en een gestapelde `created_at` zodat cadans en sessieduur kloppen
- `app/Services/DemoDataSeeder.php` — configsleutel `shots` hernoemd naar `weapon_lines`, schoten aanmaken per sessie, tellingen als constanten (`WEAPON_COUNT`, `SESSION_COUNT`, `REFLECTION_COUNT`, `SHOT_COUNT`)
- `app/Filament/Concerns/HasSeedDemoDataAction.php:31,55` — belofte en bevestiging opgebouwd uit die constanten, zodat tekst en data niet uit elkaar kunnen lopen

### Documentatie
- `docs/architecture.md` — nieuwe sectie over locale, opslag- versus weergave-tijdzone en de vastgelegde datumnotatie
- `docs/local-dev-wsl.md` — de nieuwe env-variabelen in de installatiestappen

## Tests (Pest 4)

1. `tests/Unit/Support/DateFormatTest.php` — happy: `date()`/`dateTime()`/`time()`/`month()` leveren `d-m-Y`, `d-m-Y H:i`, `H:i`, `M Y`. Faalpad: `null` in geeft `null` terug. Randgeval: een UTC-timestamp om 23:30 op 31 december komt er als 1 januari 00:30 uit (dagovergang bij de tijdzone-shift).
2. `tests/Unit/Services/Demo/DemoShotGeneratorTest.php` — happy: 30 rondes geeft 30 schoten, turn 0..2, shot_index 0..9, ring en score 0..10, coordinaten binnen 0..1. Randgeval: 0 rondes geeft een lege array; 25 rondes geeft een laatste serie van 5. Determinisme: twee aanroepen met dezelfde seed geven identieke rijen. Verhaallijn: `LEFT` geeft een gemiddelde x onder 0.5, `HIGH` een gemiddelde y onder 0.5 (y=0 is boven), `NONE` blijft gecentreerd.
3. `tests/Feature/EmptyStates/DemoDataSeederTest.php` (uitbreiden) — elke demo-sessie heeft schoten; `SessionStatsService::totalShots()` en `totalScore()` zijn groter dan 0; het totaal komt overeen met de som van `rounds_fired` én met `DemoDataSeeder::SHOT_COUNT`; de Glock-sessie toont een negatieve `meanXmm()` en de CZ-sessie een naar boven verschoven `meanYmm()`; `purgeFor()` ruimt de schoten mee op.
4. `tests/Feature/Localization/LocaleTest.php` — `app()->getLocale()` is `nl`; een `required`-fout levert een Nederlandse melding; de Filament-`nl`-vertaling is actief (een bekende sleutel is niet gelijk aan de Engelse). Faalpad: een onbekende sleutel valt terug op de `en`-fallback in plaats van de sleutelnaam.
5. `tests/Feature/Localization/TimezoneTest.php` — `config('app.timezone')` is `UTC` en `display_timezone` is `Europe/Amsterdam`; `FilamentTimezone::get()` geeft Europe/Amsterdam; regressie: een rij die als UTC in de database staat leest terug als diezelfde UTC-waarde (geen stille verschuiving) en wordt als Amsterdam-tijd weergegeven; randgeval: een tijdstip in de winter (+1) en een in de zomer (+2).
6. `tests/Feature/Localization/DateFormatRenderingTest.php` — een Filament-tabel met een kale `->date()`-kolom rendert `d-m-Y` en niet `M j, Y`; de sessie-detailpagina toont de datum in `d-m-Y`.

Draaien met het docker-commando uit de opdracht (`php artisan test --compact`, `vendor/bin/pint --dirty`,
`composer audit --format=summary`). Uitgangswaarde op `main` is lokaal geverifieerd: 472 tests geslaagd.

## Open vragen

Geen blokkerende. Eén melding voor de PR in plaats van een vraag: `resources/views/filament/resources/weapons/view-weapon.blade.php` regel 24 is contextregel binnen een hunk van PR #166, dus bij het rebasen op `main` na die merge is daar een triviaal conflict te verwachten. Dezelfde regels worden niet nog een keer aangepast; alleen de datumnotatie op die regel verandert.

## Volgende stap

`/ontwikkeling:ontwikkelen-bouwen`

# Architectuur

Laatst bijgewerkt: 2026-11-14

Deze sectie beschrijft hoe AimTrack is opgebouwd (Laravel 12, PHP 8.4/8.5, Filament 5, Livewire 4; de exacte versies staan in `composer.lock`) en hoe de belangrijkste componenten samenwerken. De app draait met één database en standaard Laravel-auth. Gebruikers kunnen lid worden van een vereniging; er is geen aparte database of subdomein per vereniging. De app is gecontaineriseerd.

## Domeinmodel
- **User** – eigenaar van de eigen sessies en wapens. Heeft optioneel een eigen Claude-key, een actieve vereniging en de vlag `is_admin` voor app-beheer.
- **Vereniging** – tabel `verenigingen`, met leden via `vereniging_user` en per lid een rol (`VerenigingRol`: lid, coach of beheerder). Kan een gedeelde, versleutelde Claude-key hebben.
- **Weapon** – naam, type, kaliber, serienummer, opslaglocatie, notities; heeft één `AiWeaponInsight` en meerdere `SessionWeapon` records.
- **Session** – datum, baan/vereniging, locatie, tijden, notities, handmatige reflectie; heeft één `AiReflection`, meerdere `SessionWeapon` records, meerdere `SessionShot` records en bijlagen.
- **SessionWeapon** – tabel `session_weapons`: koppelrecord per sessie × wapen met afstand, aantal schoten, munitie, groepering, afwijking en flyers.
- **SessionShot** – tabel `session_shots`: een individueel schot op het schotenbord.
- **Attachment** – bijlage (foto, kaart, PDF) bij een sessie.
- **AiReflection** – AI-output per sessie (samenvatting, went_well, needs_improvement, next_focus + status/provider/model/errortext).
- **AiWeaponInsight** – AI-trends per wapen (trends, typical_issues, recommendations + status/provider/model/errortext).
- **TrainingGoal** – trainingsdoel van een gebruiker, eventueel gekoppeld aan een sessie of wapen.
- **Location** en **AmmoType** – referentielijsten voor baan/locatie en munitie.
- **Coach-gesprekken** – bewaard door het Copilot-pakket in de `copilot_*`-tabellen. De eigen tabel `coach_questions` is verwijderd (`database/migrations/2026_04_26_120000_drop_coach_questions_table.php`).

## Belangrijke lagen
- **HTTP/Filament**: Filament Resources (Sessions, Weapons, Attachments, Locations, AmmoTypes, Users, CoachSessies) + Pages (Dashboard, AI-coach, Export, AI-instellingen, Mijn vereniging, Mislukte jobs). Formulieren/tafels zijn NL-gelabeld. De sessies- en wapenoverzichten tonen alleen data van de ingelogde gebruiker.
- **Livewire**: `App\Livewire\SessionShotBoard` voor het schotenbord.
- **Services**: `App\Services\Ai\ShooterCoach` voor de LLM-calls van reflectie en wapeninzicht; `AiKeyResolver` voor de keyresolutie; `Export\SessionExportService` voor CSV/PDF; `Sessions\ShotScoringService` en `SessionShotService` voor het schotenbord; `RangeConsoleSummaryService`, `SessionStatsService`, `ScoreDriftService` en `TrainingGoalService` voor overzichten en de coach; `Vereniging\VerenigingService` voor verenigingsbeheer.
- **Copilot**: `eslam-reda-div/filament-copilot` levert de coach-chat; de tools staan in `app/Filament/Copilot/Tools/`.
- **Jobs**: `GenerateSessionReflectionJob` en `GenerateWeaponInsightJob` draaien in de queue en schrijven AI-resultaten weg.
- **Config**: `config/ai.php` voor model, base_url en max tokens; `.env` plaatst secrets.
- **Foutrapportage**: Sentry (`sentry/sentry-laravel`) via het log-kanaal `sentry` en de `Queue::failing`-hook in `AppServiceProvider`.
- **Infra**: Docker Compose met nginx, php-fpm (app), Postgres/MySQL, queue-worker. Queue gebruikt database driver als standaard.

## Datastromen
- **Sessies**: gebruiker maakt sessie + repeater met wapens/afstanden → opslag in `sessions` + `session_weapons` + bijlagen in `attachments`.
- **AI-reflectie per sessie**: Filament-actie of event dispatcht `GenerateSessionReflectionJob` → job laadt sessiecontext → roept `ShooterCoach::generateSessionReflection` → bewaart `ai_reflections` met status.
- **AI-trends per wapen**: Filament-actie dispatcht `GenerateWeaponInsightJob` → job haalt gebruiksdata voor wapen op → roept `ShooterCoach::generateWeaponInsight` → bewaart `ai_weapon_insights`.
- **AI-coach**: de coachpagina toont de Copilot-chat → de tools lezen sessies, wapens en scoredrift van de ingelogde gebruiker → het gesprek wordt door het Copilot-pakket bewaard in `copilot_conversations`. De middleware `InjectUserAnthropicKey` zet de key van de gebruiker neer bij `copilot/stream`.
- **Schotenbord**: `SessionShotBoard` legt schoten vast via `SessionShotService`; `ShotScoringService` berekent de score.
- **Export**: Filament Export-page stelt de filters samen → redirect naar `exports.sessions.download` → `ExportSessionsRequest` valideert periode, formaat en wapenfilter (NL-meldingen, terug naar de exportpagina bij een fout) → `SessionExportController` roept `SessionExportService` → bouwt dataset → streamt CSV of rendert PDF view met disclaimer. De route draait achter `auth` en `throttle:10,1`, want de PDF-render is de zwaarste aanroep van de app.

## Filament resources en pages
- **SessionResource**: create/edit forms met sessiedata + repeatable session weapons + upload voor bijlagen; tabel met datum/baan/locatie/wapens/AI-status; detail toont AI-reflectie + bijlagen; een eigen pagina (`ManageSessionShots`) voor het schotenbord; actie voor AI-generatie.
- **WeaponResource**: beheer wapenstamdata, tabel met sessietelling en AI-status; detail toont AI-trends + relation manager naar sessie-wapens; actie voor AI-generatie.
- **AttachmentResource**, **LocationResource**, **AmmoTypeResource**: beheer van bijlagen, locaties en munitietypes.
- **CoachSessieResource**: "Sessies leden", alleen-lezen. Zichtbaar voor coaches en beheerders van de actieve vereniging; toont de sessies van de leden van die vereniging.
- **UserResource**: gebruikersbeheer, alleen voor app-beheerders (`users.is_admin`).
- **CoachPage**: de AI-coach (Copilot-chat) met onboardingdrempel, recente gesprekken en trainingsdoelen.
- **AiSettingsPage**: de eigen Claude-key van de gebruiker instellen en controleren.
- **VerenigingBeheer** en **VerenigingAanmaken**: "Mijn vereniging", leden en rollen beheren, een vereniging aanmaken.
- **ExportSessionsPage**: filters (van/tot, wapenselectie, formaat CSV/PDF) + downloadactie met NL-disclaimer.
- **FailedJobsPage**: overzicht van mislukte queue-jobs, alleen voor app-beheerders.

## Veiligheid en toegang tot data
- Sessies en wapens zijn van de gebruiker zelf: de resources filteren op `user_id` van de ingelogde gebruiker, en `SessionPolicy` en `WeaponPolicy` begrenzen de rest.
- Verenigingen zijn de uitzondering. `SessionPolicy::view` en `WeaponPolicy::view` laten een coach of beheerder de sessies en wapens van een lid zien als beide dezelfde actieve vereniging hebben (`User::isCoachVan`); de verenigingsbeheerder ziet bovendien namen en e-mailadressen van de leden (`VerenigingBeheer`). Dat is alleen lezen: `CoachSessieResource` staat geen aanmaken, wijzigen of verwijderen toe.
- Filters die uit een querystring komen worden door een FormRequest gevalideerd voordat ze de database raken; `ExportSessionsRequest` controleert bovendien dat elk opgegeven wapen van de ingelogde gebruiker is.
- De AI-service gebruikt de key van de gebruiker of de vereniging en een env-gestuurd model; geen secrets in code. Reflectie en wapeninzicht verlopen via de queue; de coach-chat loopt via het Copilot-pakket.
- Downloads en uploads gebruiken Laravel storage; file ACL's via storage driver. Disclaimers in exports en AI-uitvoer vermelden dat gebruiker zelf verantwoordelijk blijft.

## Taal, tijdzone en datumnotatie

AimTrack is een Nederlandstalige applicatie. `config/app.php` zet `locale` daarom op `nl` als
code-default, niet als iets dat per omgeving gezet moet worden, met `en` als fallback zodat een
ontbrekende vertaalsleutel een leesbare Engelse zin oplevert in plaats van de kale sleutelnaam. De
Nederlandse vertalingen van Laravel zelf staan in `lang/nl/` (validation, auth, passwords,
pagination); Laravel levert alleen een Engelse set mee. Filament brengt zijn eigen `nl`-bestanden mee
in vendor en gebruikt die zodra de locale goed staat.

Er zijn twee tijdzones en dat onderscheid is bewust:

| Instelling | Waarde | Waarvoor |
|---|---|---|
| `app.timezone` | `UTC` | Opslag. Laravel schrijft en leest datetime-kolommen hierin. |
| `app.display_timezone` | `Europe/Amsterdam` | Weergave. Wat de gebruiker op het scherm ziet. |

De timestamp-kolommen in Postgres dragen geen zone mee. Er staat een kale `2026-08-09 09:30:00` in
en welk moment dat voorstelt bepaalt `app.timezone` bij het teruglezen. Die vlag omzetten naar
Europe/Amsterdam verandert daarom niet het getal op het scherm, maar wel wat dat getal betekent.
Nagemeten op een rij die fysiek als `2026-08-09 09:30:00` is weggeschreven:

| Gelezen met | Wat je terugkrijgt | Welk moment is dat |
|---|---|---|
| `app.timezone = UTC` | `2026-08-09 09:30` | 09:30 UTC |
| `app.timezone = Europe/Amsterdam` | `2026-08-09 09:30` | 07:30 UTC, twee uur eerder |

Het probleem uit issue #143 wordt daar dus niet mee opgelost. Een sessie die om 10:00 Nederlandse
tijd liep staat als 08:00 in de database en zou ook na het omzetten als 08:00 op het scherm komen,
nog steeds twee uur te vroeg. Alleen rijen die na het omzetten worden geschreven zouden kloppen, en
daarmee zouden oude en nieuwe rijen in een verschillend referentiekader naast elkaar staan.

Laat die vlag daarom op UTC staan. De opslag blijft dan eenduidig en de weergave gaat via
`FilamentTimezone::set()` in `AppServiceProvider` en via `App\Support\DateFormat`, die de
opgeslagen UTC-waarde naar Europe/Amsterdam schuift. Dat is de enige variant die ook de bestaande
rijen goed toont: 08:00 UTC wordt 10:00 op het scherm.

Let op bij het kiezen van een andere `APP_DISPLAY_TIMEZONE`: Filament past de weergave-tijdzone ook
op kale datumkolommen toe (`TextColumn::date()` doet `setTimezone()`). Met Europe/Amsterdam valt dat
niet op omdat die oostelijk van UTC ligt, maar met een westelijke zone rendert elke datumkolom een
dag te vroeg.

De datumnotatie ligt vast in `App\Support\DateFormat`:

| Constante | Notatie | Gebruik |
|---|---|---|
| `DATE` | `d-m-Y` | Alle datums op het scherm. |
| `DATE_TIME` | `d-m-Y H:i` | Datum plus tijd op het scherm, in de weergave-tijdzone. |
| `TIME` | `H:i` | Alleen een tijdstip, in de weergave-tijdzone. |
| `DATE_TIME_SECONDS` | `d-m-Y H:i:s` | Alleen voor Filament-datumkiezers met seconden. |
| `TIME_SECONDS` | `H:i:s` | Alleen voor Filament-tijdkiezers met seconden. |
| `MONTH` | `M Y` | Uitzondering: trendassen die op maandbuckets draaien. |
| `MACHINE_DATE` | `Y-m-d` | Uitzondering: CSV-export en AI-tool-payloads. |

`AppServiceProvider` zet deze notaties ook als Filament-default, en daar zijn drie aanroepen voor
nodig, geen twee. `Table::configureUsing()` dekt de tabelkolommen en `Schema::configureUsing()` de
infolist-entries, want die halen hun format bij de container op. De formulierlaag doet dat niet:
`Filament\Forms\Components\DateTimePicker` draagt zijn eigen `defaultDateDisplayFormat` op de klasse
en raadpleegt de container nooit, dus een `DatePicker` binnen een schema dat op `d-m-Y` staat rendert
alsnog `MMM D, YYYY`. Daarom staat er ook een `DateTimePicker::configureUsing()`. `DatePicker` en
`TimePicker` erven van `DateTimePicker` en `ComponentManager::configure()` loopt de ouderklassen af,
dus die ene aanroep dekt alle drie de kiezers.

Kale `->date()`- en `->dateTime()`-aanroepen in resources volgen daardoor vanzelf; zonder die
configuratie vallen ze terug op Filaments eigen `M j, Y`, de Amerikaanse notatie. Zet in nieuwe code
dus geen los formaat in de aanroep, maar gebruik de kale helper of een constante uit `DateFormat`.

Toets zo'n instelling altijd aan de gerenderde pagina en niet aan de getter op `Table` of `Schema`.
Die getters stonden goed terwijl de formulierlaag er niet op aangesloten was, dus de suite was groen
terwijl de gebruiker de Amerikaanse notatie zag. `tests/Feature/Localization/DateFormatRenderingTest.php`
assert daarom op de HTML van de echte pagina.

## Uitbreidingshaken
- Extra exportprofielen kunnen via nieuwe services/views worden toegevoegd.
- Horizon of alternatieve queue backends kunnen worden aangesloten via config zonder codewijziging.

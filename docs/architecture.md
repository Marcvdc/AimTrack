# Architectuur

Deze sectie beschrijft hoe AimTrack is opgebouwd (Laravel 12, PHP 8.4/8.5, Filament 5, Livewire 4) en hoe de belangrijkste componenten samenwerken. De app is single-tenant (standaard Laravel-auth) en is gecontaineriseerd.

## Domeinmodel
- **User** – eigenaar van alle data.
- **Weapon** – naam, type, kaliber, serienummer, opslaglocatie, notities; heeft één `AiWeaponInsight` en meerdere sessie-entries.
- **Session** – datum, baan/vereniging, locatie, tijden, notities, handmatige reflectie; heeft één `AiReflection` en meerdere `SessionWeaponEntry` records.
- **SessionWeaponEntry** – koppelrecord per sessie × wapen met afstand, schoten, munitie, optionele score/groepering.
- **Attachment** – morph naar Session/SessionWeaponEntry/Weapon voor foto’s/kaarten/PDF.
- **AiReflection** – AI-output per sessie (samenvatting, went_well, needs_improvement, next_focus + status/provider/model/errortext).
- **AiWeaponInsight** – AI-trends per wapen (trends, typical_issues, recommendations + status/provider/model/errortext).
- **CoachQuestion** (optioneel) – bewaarde AI-coach vragen/antwoorden per user, met optionele wapen- en periodefilter.

## Belangrijke lagen
- **HTTP/Filament**: Filament Resources (Sessions, Weapons, AI-resultaten, Attachments) + Pages (AI Coach, Export). Formulieren/tafels zijn NL-gelabeld en filteren op de ingelogde user.
- **Services**: `App\Services\Ai\ShooterCoach` voor LLM-calls; `App\Services\Export\SessionExportService` voor CSV/PDF.
- **Jobs**: `GenerateSessionReflectionJob` en `GenerateWeaponInsightJob` draaien in de queue en schrijven AI-resultaten weg.
- **Config**: `config/ai.php` voor provider/model/base_url/timeout; `.env` plaatst secrets.
- **Infra**: Docker Compose met nginx, php-fpm (app), Postgres/MySQL, queue-worker. Queue gebruikt database driver als standaard.

## Datastromen
- **Sessies**: gebruiker maakt sessie + repeater met wapens/afstanden → opslag in `sessions` + `session_weapon_entries` + bijlagen via polymorfe relatie.
- **AI-reflectie per sessie**: Filament-actie of event dispatcht `GenerateSessionReflectionJob` → job laadt sessiecontext → roept `ShooterCoach::generateSessionReflection` → bewaart `ai_reflections` met status.
- **AI-trends per wapen**: Filament-actie dispatcht `GenerateWeaponInsightJob` → job haalt gebruiksdata voor wapen op → roept `ShooterCoach::generateWeaponInsight` → bewaart `ai_weapon_insights`.
- **AI-coach Q&A**: Filament page roept `ShooterCoach::answerCoachQuestion` aan (direct of via queue indien zwaarder) → slaat vraag/antwoord op in `coach_questions` (optioneel) en toont antwoord.
- **Export**: Filament Export-page valideert filters → roept `SessionExportService` → bouwt dataset → streamt CSV of rendert PDF view met disclaimer.

## Filament resources en pages
- **SessionResource**: create/edit forms met sessiedata + repeatable session weapon entries + upload voor bijlagen; tabel met datum/baan/locatie/wapens/AI-status; detail toont AI-reflectie + bijlagen; actie voor AI-generatie.
- **WeaponResource**: beheer wapenstamdata, tabel met sessietelling en AI-status; detail toont AI-trends + relation manager naar sessie-entries; actie voor AI-generatie.
- **AiReflectionResource / AiWeaponInsightResource**: read-only inzicht in AI-status (audit/controle).
- **AttachmentResource**: optionele read-only lijst van uploads.
- **AiCoachPage**: vrije vraag + optionele wapen/periode; toont antwoord + waarschuwing dat AI geen vervanging is voor veiligheidsregels.
- **ExportSessionsPage**: filters (van/tot, wapenselectie, formaat CSV/PDF) + downloadactie met NL-disclaimer.

## Veiligheid en multi-tenancy
- Elke query filtert op `user_id` van de ingelogde gebruiker; Filament resources gebruiken scopes/policies om data te isoleren.
- AI-service gebruikt env-gestuurde provider/model; geen secrets in code. AI-calls verlopen via queue (async) behalve optionele coachvragen.
- Downloads en uploads gebruiken Laravel storage; file ACL’s via storage driver. Disclaimers in exports en AI-uitvoer vermelden dat gebruiker zelf verantwoordelijk blijft.

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
- Multi-turn coachgesprekken kunnen de `coach_threads/messages` structuur benutten.
- Horizon of alternatieve queue backends kunnen worden aangesloten via config zonder codewijziging.

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
- Verenigingen zijn de enige uitzondering. `SessionPolicy::view` laat een coach of beheerder de sessie van een lid zien als beide dezelfde actieve vereniging hebben (`User::isCoachVan`). Dat is alleen lezen: `CoachSessieResource` staat geen aanmaken, wijzigen of verwijderen toe.
- Filters die uit een querystring komen worden door een FormRequest gevalideerd voordat ze de database raken; `ExportSessionsRequest` controleert bovendien dat elk opgegeven wapen van de ingelogde gebruiker is.
- De AI-service gebruikt de key van de gebruiker of de vereniging en een env-gestuurd model; geen secrets in code. Reflectie en wapeninzicht verlopen via de queue; de coach-chat loopt via het Copilot-pakket.
- Downloads en uploads gebruiken Laravel storage; file ACL's via storage driver. Disclaimers in exports en AI-uitvoer vermelden dat gebruiker zelf verantwoordelijk blijft.

## Uitbreidingshaken
- Extra exportprofielen kunnen via nieuwe services/views worden toegevoegd.
- Horizon of alternatieve queue backends kunnen worden aangesloten via config zonder codewijziging.

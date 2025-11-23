# Architectuur

Deze sectie beschrijft hoe AimTrack is opgebouwd (Laravel 12, PHP 8.4/8.5, Filament 4, Livewire 3) en hoe de belangrijkste componenten samenwerken. De app is single-tenant (standaard Laravel-auth) en is gecontaineriseerd.

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

## Uitbreidingshaken
- Extra exportprofielen kunnen via nieuwe services/views worden toegevoegd.
- Multi-turn coachgesprekken kunnen de `coach_threads/messages` structuur benutten.
- Horizon of alternatieve queue backends kunnen worden aangesloten via config zonder codewijziging.

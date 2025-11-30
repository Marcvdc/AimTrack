# AimTrack Plan (PLAN-FIRST)

## 1) Korte beschrijving
AimTrack is een persoonlijke schietlog-app (Laravel 12 + Filament 4) waarmee een sportschutter sessies kan registreren, reflecteren en AI-ondersteuning krijgt op basis van eigen data. De app is self-hosted, privacy-first en biedt exports (CSV/PDF) voor WM-4-achtige rapportages.

## 2) Belangrijkste use-cases
- **Schietlog sessie registreren:** datum, baan/vereniging, locatie, munitie, meerdere (wapen × afstand) entries, ruwe notities, bijlagen (foto/kaart/PDF).
- **Reflectie + AI-reflectie bekijken:** korte handmatige reflectie, AI-samenvatting met leerpunten, focus voor volgende keer.
- **Wapenbeheer:** wapens (type/kaliber/serienummer/opslag/notities) koppelen aan sessies; AI-trends per wapen.
- **AI-coach:** vrije vragen stellen over eigen logs; AI genereert context uit sessies en wapens via ShooterCoach-service en queue jobs.
- **Export WM-4-achtig:** selecteer periode + wapens; genereer CSV/PDF met sessies, aantallen, munitie, locaties + disclaimer.

## 3) Domeinmodel
**Tabellen + velden (globaal):**
- `users` (Laravel default)
- `weapons`: id, user_id (fk), naam, type (enum/string), kaliber (string), serienummer, opslaglocatie, notities (text), purchased_at (date), retired_at (date|null), timestamps.
- `sessions`: id, user_id (fk), datum (date), locatie (string), baan/vereniging (string), weather (string|null), munitie_merk (string|null), handmatige_reflectie (text|null), raw_notes (longtext|null), start_time/end_time (time|null), timestamps.
- `session_weapon_entries`: id, session_id (fk), weapon_id (fk), afstand_m (integer), aantal_schoten (integer), munitie_kaliber (string), score (integer|null), grouping_note (string|null), timestamps.
- `attachments`: id, attachable_type/id (morph), user_id (fk), path, mime, size, original_name, timestamps.
- `ai_reflections`: id, session_id (unique fk), summary (text), went_well (text), needs_improvement (text), next_focus (text), provider (string), model (string), status (enum pending/succeeded/failed), error (text|null), generated_at (datetime|null), timestamps.
- `ai_weapon_insights`: id, weapon_id (unique fk), trends (text), typical_issues (text), recommendations (text), provider (string), model (string), status (enum pending/succeeded/failed), error (text|null), generated_at (datetime|null), timestamps.
- `coach_threads`: id, user_id, topic (string), timestamps (optioneel voor toekomstige chat-threading).
- `coach_messages` (optioneel MVP-lite): id, coach_thread_id (fk), role (user/assistant/system), content (text), tokens (integer|null), timestamps.

**Relaties:**
- User hasMany Weapons, Sessions, Attachments, CoachThreads.
- Weapon belongsTo User; hasMany SessionWeaponEntries; hasOne AiWeaponInsight.
- Session belongsTo User; hasMany SessionWeaponEntries & Attachments; hasOne AiReflection.
- SessionWeaponEntry belongsTo Session & Weapon.
- Attachment morphTo attachable (Session, SessionWeaponEntry, Weapon) and belongsTo User.
- CoachThread belongsTo User; hasMany CoachMessages.
- AiReflection belongsTo Session; AiWeaponInsight belongsTo Weapon.

## 4) Filament-structuur
**Resources:**
- **SessionResource**
  - Form: datum, locatie, baan, munitie, tijdsblokken, handmatige reflectie, repeatable SessionWeaponEntries (wapen, afstand, aantal schoten, munitie, score), bijlagen upload.
  - Table: datum, locatie, gekoppelde wapens, aantal entries, AI-status, acties (bekijk, AI-reflectie genereren, export per sessie).
- **WeaponResource**
  - Form: naam, type, kaliber, serienummer, opslaglocatie, aankoopdatum, notities.
  - Table: naam, kaliber, gebruiksteller (aantal sessies/entries), AI-inzicht-status, acties (bekijk, AI-trends genereren).
- **AttachmentResource** (optioneel beheer): lijst/download.
- **AiReflectionResource** (alleen read): status, provider/model, timestamps.
- **AiWeaponInsightResource** (alleen read): status, provider/model, timestamps.

**Pages (Filament Pages):**
- **AI-CoachPage:** free-form vraag, optioneel keuze sessie/wapen context, antwoordpaneel, historie (coach_threads/messages) zodra beschikbaar.
- **ExportPage:** parameters (periode, selectie wapens), formaatkeuze (CSV/PDF), downloadactie + disclaimer.

## 5) AI-architectuur
- Service: `App\Services\Ai\ShooterCoach` (LLM-klant; provider/model/config uit `config/ai.php` + .env).
- Queue Jobs: `GenerateSessionReflectionJob` (input: session_id) en `GenerateWeaponInsightJob` (input: weapon_id); beide halen context op, roepen ShooterCoach aan, schrijven resultaten naar respectieve tabellen en markeren status.
- Triggering: Filament actieknoppen ("Genereer AI-reflectie", "Genereer AI-trends"), plus event listener na sessie-aanmaak kan job dispatchen (optioneel).
- Opslag AI-resultaten: tabellen `ai_reflections` en `ai_weapon_insights` (tekstvelden voor structured text of JSON-lite). Voor coach Q&A: `coach_threads` + `coach_messages` (optioneel in MVP-matrix, minimaal transient weergave).
- Veiligheid/limieten: provider key via .env; timeouts en max tokens in config; jobs herproberen bij falen; audit fields (provider/model/status/error).

## 6) Export-architectuur
- Flow: Filament `ExportPage` → valideert filters → roept `App\Services\Exports\SessionExportService` aan → service haalt sessies + weapon entries op → bouwt dataset + disclaimer → levert als CSV of PDF (via Laravel Excel/Dompdf of simpele generator) → Filament page retourneert streamed download.
- Queue (optioneel bij grote datasets): export job genereren en downloadlink e-mailen; MVP: synchrone download zolang dataset klein blijft.

## 7) MVP-scope vs later
- **MVP:** sessions + weapon entries + attachments; AI-reflectie per sessie (queue); AI-trends per wapen (queue); AI-coach single-turn (prompt + antwoord); basic CSV/PDF export; Filament resources/pages; Docker Compose met queue worker; basis-auth.
- **Later:** multi-turn coach threads met historie; tagging/munitie-inventaris; kalenderweergave; notificaties; geavanceerde PDF templates; role-based sharing; offline-first mobile; analytics dashboards; export job + e-mail delivery.

## 8) Volgende stappen
1. Migrations voor users/weapons/sessions/session_weapon_entries/attachments/ai_reflections/ai_weapon_insights/coach_threads/coach_messages.
2. Models + Eloquent-relaties + factories (waar zinvol).
3. Config `config/ai.php` + service `App\Services\Ai\ShooterCoach` (provider stub, env-driven).
4. Queue jobs `GenerateSessionReflectionJob` en `GenerateWeaponInsightJob` + event/listener hooks.
5. Filament Resources (SessionResource, WeaponResource, AiReflectionResource, AiWeaponInsightResource, AttachmentResource) + Pages (AI-CoachPage, ExportPage).
6. Export service + Filament downloadflow (CSV/PDF) + disclaimer.
7. Seeders/tests (basis smoke), Docker Compose/Makefile voor dev + queue worker.

## 9) Filament admin-plan (concreet voor deze iteratie)
- **PanelProvider:** `App\Providers\Filament\AdminPanelProvider` met default auth (Laravel), locale nl, kleuraccent donkerblauw; navigation met Sessions & Wapens bovenaan.
- **SessionResource:**
  - Form: sectie "Sessie" (datum, baan/vereniging `range_name`, locatie, notities `notes_raw`, handmatige reflectie), relation `user_id` hidden default current user.
  - Repeatable/hasMany form voor `session_weapons` (select wapen, afstand, afgevuurde patronen, ammo type, group quality text, deviation enum, flyers count).
  - File upload voor bijlagen (foto/pdf) gekoppeld aan session attachments; toon lijst in tabel of via relation manager.
  - Table: datum, baan, locatie, wapens (chips), totaal schoten, AI-reflectie status; filters op periode, wapen, afwijking/kaliber (where relevant via relationship).
  - Show view: tabs Details / AI-reflectie; AI-tab toont `summary`, `positives`, `improvements`, `next_focus` + actie "AI-reflectie opnieuw genereren" die job dispatcht.
- **WeaponResource:**
  - Form: naam, type (enum `WeaponType`), kaliber, serienummer, opslaglocatie, aankoopdatum, actief toggle, notities; user default current.
  - Table: naam, type, kaliber, actief, sessietelling, AI-inzicht status; filters op type, kaliber, actief.
  - Show view: sectie "Sessies" via relation manager op `sessionWeapons`; sectie "AI-inzichten" met `summary`, `patterns`, `suggestions` + actie "AI-inzichten genereren" die job dispatcht.
- **AttachmentResource (optioneel):** read-only listing van uploads indien nodig; primair integreren via SessionResource file upload component.
- **Jobs/hooks:** actions dispatchen queue jobs `GenerateSessionReflectionJob` en `GenerateWeaponInsightJob`; jobs stubs voorzien totdat AI-service is ingevuld.

## 11) Export-scope voor WM-4 (huidige iteratie)
- **Service:** `App\Services\Export\SessionExportService` met methode `exportSessions(User $user, Carbon $from, Carbon $to, ?array $weaponIds, string $format)` die sessies binnen periode ophaalt (optionele wapenfilter) en een downloadresponse teruggeeft.
- **CSV-output:** kolommen `datum, baan, locatie, wapen, kaliber, afstand (m), rondes, munitietype, groepering, afwijking, flyers, notities`; per sessiewapen een regel.
- **PDF-output:** eenvoudige Blade-view (`resources/views/exports/sessions.blade.php`) met periode-overzicht, totalen per wapen/kaliber, lijst van sessies en NL-disclaimer: "Let op: dit document is een hulpmiddel; controleer altijd zelf of dit voldoet aan de actuele eisen van de politie / korpschef voor een WM-4 aanvraag." PDF-rendering via een lichte lib (bijv. Dompdf) zonder zware styling.
- **Filament Page:** `App\Filament\Pages\ExportSessionsPage` met formulier (van/tot, multi-select wapens, formaatkeuze CSV/PDF) en actie die service aanroept en download terugstuurt.
- **Uitbreidbaarheid:** structuur laten voor extra exportprofielen (aparte view/logica), en rekening houden met single-tenant (alle queries gefilterd op ingelogde gebruiker).

## 10) Iteratieplan: AI-service + jobs + Filament acties
- **Service:** implementeer `App\Services\Ai\ShooterCoach` met generieke LLM-client (configurable driver/model/base_url) en NL-prompts voor sessie-reflectie, wapen-inzichten en coachvragen. Output parse defensief (JSON → fallback tekst) en log fouten.
- **Config:** nieuw `config/ai.php` met driver/model/base_url; .env.example uitbreiden met `AI_DRIVER`, `AI_MODEL`, `OPENAI_API_KEY`, `OPENAI_BASE_URL` placeholders.
- **Queue jobs:** `GenerateSessionReflectionJob` en `GenerateWeaponInsightJob` roepen de service aan en verwerken resultaten in respectieve modellen/velden.
- **Filament actions:** in `SessionResource` en `WeaponResource` extra actions om jobs te dispatchen (AI-calls blijven async).

## 12) Iteratie: AI-Coach vrije vragen page (huidige taak)
- **Doel:** Filament-pagina `AiCoachPage` waar gebruiker vrije vragen kan stellen over eigen sessies/wapens, met optionele filters (wapen + periode) en veiligheid/disclaimer in UI + prompt.
- **Datamodel:** nieuwe tabel `coach_questions` met `id, user_id (fk), weapon_id (nullable), question, answer, asked_at (timestamp), period_from/to (nullable), created_at/updated_at`. Relaties: User hasMany CoachQuestions; Weapon hasMany CoachQuestions; CoachQuestion belongsTo User en Weapon.
- **Service-uitbreiding:** `ShooterCoach::answerCoachQuestion(User $user, string $question, ?int $weaponId = null, ?Carbon $from = null, ?Carbon $to = null)` bouwt context: relevante sessies van user gefilterd op periode/wapen + recente wapen entries; prompt benadrukt veiligheid/geen illegale tips en dat AI geen vervanging is voor instructeurs/regels. Retourneert antwoordstring.
- **Filament Page:** `AiCoachPage` met textarea vraag, select wapen (optioneel), date range filter, submit-button "Stel vraag aan AI-coach", resultaatkaart met antwoord (en waarschuwing dat AI geen vervanging is). Na submit: roept ShooterCoach aan, slaat vraag/antwoord op in `coach_questions` en toont laatste antwoord. Toon recente historie (bijv. table/list van vorige Q/A van gebruiker) voor context.
- **Veiligheid:** System prompt + UI bevatten disclaimer over veiligheid, geen illegale/gevaarlijke adviezen; antwoord max ~150 woorden. Geen queue-job nodig (synchrone vraag), maar blijf defensief bij API-fouten (toon melding).

## 13) Infra: Docker Compose voor lokale ontwikkeling
- **Containers:**
  - `app`: PHP 8.4/8.5 FPM + benodigde extensies (pdo, pdo_pgsql/pdo_mysql, gd, mbstring, intl, zip, exif), Composer aanwezig; mount project naar `/var/www/html`.
  - `web`: nginx die `/public/index.php` serveert en requests proxyt naar `app:9000`.
  - `db`: PostgreSQL (default) met volume voor data; MySQL kan later als alternatief.
  - `queue`: hergebruikt het app-image en draait `php artisan queue:work --tries=3`.
- **Flow:** `docker compose up -d` start stack; daarna `docker compose exec app php artisan key:generate` en `docker compose exec app php artisan migrate` om de app klaar te zetten.
- **ENV-waarden:** `APP_URL=http://localhost:8080`, `DB_HOST=db`, `DB_PORT=5432`, `QUEUE_CONNECTION=database`, optioneel `VITE_*` voor asset build; AI-variabelen blijven in `.env` staan.

## 14) Stabilisatieplan (eindcontrole)
- **Relaties en scope:** Sessions, Weapons, SessionWeapons, AiReflection en AiWeaponInsight blijven strikt gekoppeld aan de ingelogde gebruiker; Filament queries filteren op `user_id` of gekoppelde sessies.
- **AI-flow robuust:** `ShooterCoach` via service container met config uit `config/ai.php`; jobs/pages delen dezelfde service; defensieve foutafhandeling blijft.
- **Filament polish:** Forms tonen vereiste velden, repeaters werken end-to-end, relation managers bieden filters en CRUD; detailpagina’s tonen AI-output en bijlagen overzichtelijk.
- **Export afronden:** CSV/PDF gebruiken dezelfde dataset/filtering, tonen disclaimer en aggregaties; Filament exportpagina valideert periode en selectie.
- **Opschonen:** Geen TODO’s of losse eindjes; labels NL; defaults voor datumfilters (bijv. huidige maand) en statusnotificaties bij async AI-acties.

## 15) Kwaliteit & CI (huidige iteratie)
- **Lintingstandaard:** Laravel Pint configureren met Laravel preset + projectbrede aanpassingen (max line length/logische imports waar nodig). Composer scripts `lint` (Pint) en `lint:fix` toevoegen en `test` laten verwijzen naar Pest.
- **Automatische checks:** GitHub Actions workflow met PHP 8.3/8.4 matrix; stappen voor Composer cache, install, `php artisan key:generate`, `php artisan migrate --env=testing` met in-memory/SQLite of DB volgens default; run `pest` en `pint --test`.
- **Doel:** waarborgen consistente codekwaliteit en snelle feedback in CI voor lint + testen.

## 16) Release & productiehardening (huidige iteratie)
- **Multi-stage Docker image:** builder met Composer install (zonder dev), runtime met PHP-FPM 8.4/8.5, opcache en minimale surface. Entry script forceert `php artisan optimize` (config/route/view cache) bij start in production mode.
- **Docker Compose (dev):** nginx + php-fpm + Postgres + queue worker met `restart: unless-stopped`, shared volumes, `.env` dev defaults. HTTPS headers en trusted proxies geconfigureerd zodat reverse proxies en SSL-terminatie goed werken.
- **Release workflow:** GitHub Actions `docker-release` op push naar `main`; bouwt en pusht image naar GHCR met tags `latest` + semver (laatste git tag fallback), gebruikt provenance cache waar mogelijk.
- **Prod defaults:** `.env.example` op `APP_ENV=production`, `APP_DEBUG=false`, DB user met beperkte rechten. Force HTTPS via config flag en trusted proxies; queue worker persistent; AI timeouts/retries + defensieve fallback bij netwerkfouten.
- **Checklist "Application Hardened":** document in `docs/` met concrete stappen (keys/APP_KEY, caches, storage permissions, DB user, HTTPS/headers, mail/queue readiness, backups/logging) die voor livegang moeten zijn afgevinkt.

## 17) Monitoring & logging + UX-final pass (huidige iteratie)
- **Healthcheck:** publieke (unauthenticated) endpoint `/health` die DB-connectiviteit, queue-config en storage schrijfbaarheid verifieert en `200 OK` + JSON teruggeeft; geen gevoelige data. Te gebruiken voor container/orchestration probes.
- **Loggingstrategie:** Sentry als primaire error-tracker met env-toggle; val terug op structured file logging (Stack driver) en `stderr` voor containers. Config in `config/logging.php` en `.env.example` placeholders voor DSN/sample rate. Alle queue-jobs loggen failures; AI-service errors krijgen breadcrumbs.
- **Queue monitoring:** Laravel Horizon optioneel, maar minimaal `queue:failed` view + Filament dashboard widget die recente mislukte jobs toont. Horizon config voorbereiden maar niet verplicht voor runtime.
- **Back-ups:** documenteer in `docs/` een pragmatische strategie voor database + storage (bijv. pg_dump cron + object storage sync) inclusief frequentie en herstelstappen. Geen code-automation nu, alleen checklist/strategie.
- **UX final pass:**
  - NL-labels nalopen in Filament resources/forms, navigatie groeperen (bijv. "Dagboek" voor sessies, "Beheer" voor wapens/AI-inzichten).
  - AI-output visueel onderscheiden (badge "AI" + aparte kaart/tabelsectie) en user-notities duidelijk labelen.
  - Foutmeldingen verduidelijken in Livewire/Filament (notifications met NL teksten, duidelijke empty states).
  - Export lay-out check: PDF/CSV bevat disclaimers en nette kolomtitels (NL), basisstijl in Blade-view.

**Aanpak volgorde:**
1. Healthcheck route + controller toevoegen incl. storage write-check en DB ping.
2. Logging/Sentry configureren (`config/logging.php`, `.env.example`, `config/sentry.php` indien nodig) + queue failure logging.
3. Queue monitoring: eenvoudige Filament widget voor recente failed jobs en link naar `queue:failed` (CLI) of Horizon stub.
4. Backupstrategie uitschrijven in `docs/BACKUPS.md` (DB + storage + herstelstappen).
5. UX-polish: labels/navigatie in Filament, AI-output badges, duidelijke meldingen, export PDF/CSV labels/disclaimer en leeg-states.

## 18) Accountbeheer: wachtwoord reset + profiel (huidige iteratie)

### Scope

- **In scope**
  - Zelfservice **wachtwoord reset** voor de gebruiker via een "Wachtwoord vergeten?"-link op het login-scherm.
  - **Eigen profielbeheer** in het Filament admin panel: ingelogde gebruiker kan eigen e-mail en wachtwoord wijzigen.
- **Niet in scope (nu)**
  - Beheren/wijzigen van andere gebruikers (alleen eigen account).
  - Extra features zoals e-mailverificatie na wijziging en 2FA.

### Functionele aanpak

- **Wachtwoord reset (self-service)**
  - Gebruik Laravel's standaard password-reset flow met tokens in de database en e-mail met reset-link.
  - Login-pagina krijgt een link "Wachtwoord vergeten?" die leidt naar een formulier waar de gebruiker zijn/haar e-mailadres invult.
  - Bij een bekend e-mailadres wordt een reset-e-mail verstuurd (bij onbekend adres wordt een generieke melding getoond).
  - Via de reset-link komt de gebruiker op een formulier waar een nieuw wachtwoord kan worden ingesteld; na succesvolle reset kan de gebruiker inloggen met het nieuwe wachtwoord.

- **Profielpagina in Filament (eigen gebruiker)**
  - Filament-pagina of profielscherm "Mijn profiel" voor de ingelogde gebruiker.
  - Sectie **Persoonlijke gegevens**: velden `name` en `email` met validatie (unieke, geldige e-mail).
  - Sectie **Wachtwoord wijzigen**: velden huidig wachtwoord, nieuw wachtwoord en bevestiging.
  - Huidig wachtwoord wordt gevalideerd (bijv. via `Hash::check`); bij succes wordt het nieuwe wachtwoord veilig gehashed opgeslagen (gebruik makend van de bestaande `password` cast op het `User`-model).

### Acceptatiecriteria

1. **Wachtwoord vergeten**
   - Op het login-scherm is een duidelijke link "Wachtwoord vergeten?" zichtbaar.
   - Bij invoer van een geldig e-mailadres ontvangt de gebruiker een reset-e-mail met een unieke, tijdsgebonden link.
   - Bij onbekend e-mailadres wordt een generieke melding getoond zonder prijs te geven of het adres bestaat.
   - Een reset-link is eenmalig te gebruiken en vervalt na verloop van tijd (standaard Laravel-gedrag).

2. **E-mail wijzigen in profiel**
   - De ingelogde gebruiker kan in het admin panel zijn/haar eigen e-mailadres wijzigen.
   - De nieuwe e-mail moet uniek zijn in `users.email` en in een geldig formaat.
   - Na wijziging wordt het nieuwe e-mailadres direct opgeslagen en gebruikt voor toekomstige logins.

3. **Wachtwoord wijzigen in profiel**
   - De ingelogde gebruiker moet het huidige wachtwoord correct invoeren om een nieuw wachtwoord te kunnen instellen.
   - Het nieuwe wachtwoord wordt veilig gehashed opgeslagen via de bestaande password-cast.
   - Na wijziging werkt uitsluitend het nieuwe wachtwoord voor login; (optioneel) andere sessies/devices worden ongeldig gemaakt.

4. **Beveiliging / non-functional**
   - Password-reset requests worden beperkt (rate limiting/throttle) om misbruik te beperken.
   - Er worden geen wachtwoorden of reset-tokens gelogd; foutmeldingen blijven generiek.


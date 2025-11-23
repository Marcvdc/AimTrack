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

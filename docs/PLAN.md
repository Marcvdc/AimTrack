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

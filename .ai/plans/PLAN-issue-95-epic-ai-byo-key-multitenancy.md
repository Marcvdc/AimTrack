---
status: APPROVED (design)
created: 2026-06-06
updated: 2026-06-06
approved: 2026-06-06 (Marcvdc — ontwerp)
author: Claude (Opus 4.8, 1M context)
jira: GH#95
worktree: aimtrack-ai-byo-key (feature/ai-byo-key)
basis: 128186e (main)
---

# PLAN: Issue #95 · Epic — AI BYO-key + multi-tenancy verenigingen

## Status: DESIGN APPROVED — implementatieplan Fase 1 volgt

Ontwerp goedgekeurd door Marcvdc (brainstorm 2026-06-06). Dit is het
epic-overzicht; het gedetailleerde implementatieplan voor **Fase 1** komt in
`PLAN-issue-95-fase-1-ai-byo-key.md` (via writing-plans). Fase 2 blijft
visie-niveau tot prio 3 aan de beurt is.

---

## Context & uitgangssituatie

AimTrack is nu **single-tenant-per-user**: elk domeinmodel (Session, Weapon,
SessionShot, AiReflection, Location, AmmoType…) hangt aan `user_id`. Er is
géén vereniging/club/tenant-concept en geen tenancy- of permission-package.

Er zijn **twee** AI-integraties:
- `app/Services/Ai/ShooterCoach.php` → **OpenAI** (`gpt-4.1-mini`, global
  `OPENAI_API_KEY`) voor sessie-reflecties en wapen-insights (async jobs).
- **Filament Copilot plugin** → **Anthropic Claude** (`claude-haiku-4-5`,
  global `ANTHROPIC_API_KEY`) voor de chat-UI, in `AdminPanelProvider`.

Feature-flag `aimtrack-ai` (Pennant, global scope) gatet de AI-features via
`app/Support/Features/AimtrackFeatureToggle.php`.

## Kernidee

AimTrack wordt geschikt voor meerdere schietverenigingen, en **alle AI draait
op een eigen Anthropic (Claude) API-key** van de gebruiker/vereniging — geen
gedeelde centrale sleutel. Dat dekt in één keer alle vier de drijfveren:
kosten verschuiven, data-controle/privacy, rate-limit-isolatie én
AI-ontgrendeling (geen key = geen AI).

**Resolutie van de actieve key:** `user-key → verenigings-key → geen AI`.

---

## Fase 1 — BYO Claude API-key per user (prio 2)

**Doel:** elke gebruiker vult z'n eigen Anthropic-key in. *Alle* AI loopt via
die key: Copilot-chat én sessie-reflecties én wapen-insights. Inclusief
migratie van `ShooterCoach` van OpenAI naar Claude → één provider, één key.

### Componenten
1. **Key-opslag** — kolom `users.anthropic_api_key` (nullable, `encrypted`
   cast) + `ai_key_verified_at`. Nooit plain opslaan, nooit volledig tonen.
2. **Instellingen-UI (Filament)** — profielpagina om de key te plakken, met
   "test key"-actie (goedkope Anthropic-call) die slaagt/faalt toont. Key
   gemaskeerd (`sk-ant-…1234`), vervangen/wissen mogelijk. NL teksten.
3. **`AiKeyResolver` service** — centrale resolutie van de actieve key
   (Fase 1: `user-key → null`). Zowel `ShooterCoach` als Copilot gebruiken deze.
4. **AI-beschikbaarheidsgate** — AI "aan" = flag `aimtrack-ai` actief **én**
   een key resolved. `AimtrackFeatureToggle` krijgt de key-check; Copilot's
   `authorizeUsing(aiEnabled())` wordt het enige chokepoint.
5. **`ShooterCoach` → Claude** — OpenAI-HTTP-call vervangen door Anthropic
   Messages API via raw Laravel `Http` (geen nieuwe SDK). Prompts porten
   (system + user, JSON-output). Model `claude-haiku-4-5-20251001`.
   `config/ai.php` driver → `anthropic`; key per-request uit de resolver.
6. **Copilot per-user key-injectie** ⚠️ *technisch risico* — plugin zet
   provider/model nu statisch in `AdminPanelProvider` en leest
   `ANTHROPIC_API_KEY` uit env. Key van de huidige gebruiker runtime
   injecteren (closure/config-override via middleware). **Eerste build-stap
   om te de-risken.**
7. **Jobs & security** — `GenerateSessionReflectionJob` /
   `GenerateWeaponInsightJob` resolven de key op `handle()`-tijd uit de DB
   (Session → user). **Key nooit in de job-payload serialiseren.**
8. **Foutafhandeling/UX** — geen key + flag aan → vriendelijke prompt "Vul je
   Claude-key in bij Instellingen". Ongeldige key → graceful fail via bestaand
   `AiCoachFailureNotification`-patroon.

### Acceptatiecriteria
- [ ] Gebruiker kan in Instellingen een Claude-key opslaan; encrypted opgeslagen, alleen gemaskeerd getoond.
- [ ] "Test key"-actie valideert tegen Anthropic met duidelijke succes/fout-feedback.
- [ ] `AiKeyResolver` geeft de user-key terug, of `null` als die ontbreekt.
- [ ] AI-features beschikbaar dan en slechts dan als `aimtrack-ai` actief is én een key resolved.
- [ ] Copilot gebruikt de key van de ingelogde gebruiker, niet een centrale env-key.
- [ ] Sessie-reflecties en wapen-insights via Claude, met dezelfde output-velden als nu.
- [ ] Jobs resolven de key runtime uit de DB; key staat niet in de geserialiseerde payload.
- [ ] Zonder key tonen de AI-schermen een nette prompt i.p.v. een fout.
- [ ] Pest-tests dekken resolver, settings-opslag/masking/test-actie, gate, Copilot-autorisatie, ShooterCoach-generatie (Anthropic gemockt), job-key-resolutie, graceful failure.
- [ ] Na de migratie is OpenAI ongebruikt; `OPENAI_*` config gemarkeerd als deprecated.

### Buiten scope Fase 1
Verenigingen, rollen, gedeelde keys.

---

## Fase 2 — Multi-tenancy: verenigingen met coach-inzage (prio 3)

**Doel:** meerdere schietverenigingen. Leden houden eigendom van hun data;
coaches/clubbeheerders zien sessies & voortgang van leden bínnen hun
vereniging. Gedeelde verenigings-key (fallback onder de user-key). Ledenbeheer.

### Componenten
1. **Datamodel** — `verenigingen` (id, naam, slug, `anthropic_api_key`
   encrypted nullable, settings). Pivot `vereniging_user` (vereniging_id,
   user_id, `role` enum: `member`/`coach`/`admin`, joined_at). Bestaande
   domeindata blijft `user_id`-owned — **géén schema-brede `tenant_id`-
   refactor**. v1: één actieve vereniging per user (pivot laat later meerdere toe).
2. **Rollen & autorisatie** — member (eigen data), coach (read-only inzage in
   leden binnen dezelfde vereniging), admin (coach + ledenbeheer +
   verenigings-key beheren). Home-grown policies/gates op pivot-rol +
   zelfde-vereniging (geen spatie/permission).
3. **Data-toegang/scoping** — coach-inzage als **aparte read-only** screens/
   scopes, i.p.v. de bestaande `->where('user_id', auth()->id())` oprekken
   (voorkomt cross-user lekken). Eigen schermen van de schutter onveranderd.
4. **Verenigings-key in resolver** — `AiKeyResolver` uitgebreid naar
   `user-key → vereniging-key → null`. Club-admin beheert de gedeelde key.
5. **Ledenbeheer-UI** — admin nodigt leden uit (op e-mail) / voegt toe, wijst
   rollen toe, beheert gedeelde key, optioneel usage-inzicht.
6. **Feature-flag scope** — `aimtrack-ai` kan later vereniging-scoped
   (Pennant scopes) — enhancement.

### Acceptatiecriteria
- [ ] Vereniging aanmaakbaar met naam en (optioneel) eigen Claude-key.
- [ ] Gebruikers worden lid met een rol (member/coach/admin).
- [ ] Coach ziet read-only sessies & voortgang van leden binnen dezelfde vereniging.
- [ ] Coach ziet géén data van andere verenigingen; member ziet géén data van peers.
- [ ] Key-resolutie volgt `user-key → vereniging-key → geen AI`.
- [ ] Club-admin beheert leden (toevoegen/uitnodigen, rollen) en de gedeelde key.
- [ ] Pest-tests dekken membership/rollen, coach-inzage-grenzen, cross-tenant-isolatie, key-precedentie.

### Sub-fasering (plan later)
(a) verenigingen + membership + rollen + key-resolutie → (b) coach-inzage-
schermen → (c) ledenbeheer-UI.

---

## Ontwerpbesluiten (defaults)
- Raw Laravel `Http` voor Anthropic, geen nieuwe SDK.
- Reflectie-model `claude-haiku-4-5-20251001`.
- Geen centrale fallback-key; AI puur BYO (optionele operator-globale env-key default uit).
- v1: één actieve vereniging per user.
- Home-grown rollen (pivot-enum + policies), geen spatie/permission.
- Jobs resolven key runtime uit DB; key nooit in payload.

## Aanpak / borging
- Worktree: `aimtrack-ai-byo-key` (`feature/ai-byo-key`), basis `128186e` (main).
- Fase 2 krijgt later een eigen worktree.
- GH-epic: #95.

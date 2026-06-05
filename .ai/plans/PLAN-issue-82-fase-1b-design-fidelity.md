---
status: APPROVED
created: 2026-06-05
updated: 2026-06-05
approved: 2026-06-05 (Marcvdc)
author: Claude (Opus 4.8 1M)
jira: GH#82
worktree: aimtrack-design-fidelity (feature/design-fidelity)
basis: c640d0c (main, post-PR #91)
---

# PLAN: Issue #82 · Fase 1b — Design-fidelity & AI-stuk

## Status: APPROVED — BUILD gestart 2026-06-05

## Aanleiding

Fase 1 (Range Console, PR #88) is functioneel "COMPLETED" maar de gebruiker
meldt: _"komt in de buurt maar we zijn er echt niet"_ en _"de AI stuk"_ moet
worden aangevuld + issue #82 moet overeenkomen.

Een gestructureerde gap-analyse (7 agents: 5 schermen + theme/shell + AI-backend)
gaf fidelity-scores (0–100) t.o.v. het design-handoff:

| Scherm | Fidelity | Grootste tekorten |
|---|---|---|
| Wapen-detail | 72 | tabel-kolommen, kv-rijen, trend-as |
| Sessie-detail | 62 | EINDSCORE-delta, header-meta, AI-knoppen, X/Y/SD |
| Sessies-overzicht | 52 | **stock Filament-tabel** i.p.v. dense grid, **Wapens-kaart mist** |
| AI-stuk (backend) | 52 | **enum-crash**, prompt mist schotdata, insights niet getoond |
| AI-coach (UI) | 38 | static launcher i.p.v. chat-beleving |
| Wizard | 38 | chrome niet donker, preview-stap |
| Theme/shell | 38 | **Filament-chrome consumeert tokens niet** |

### Drie systemische root causes (verklaren ~alle "niet"-gevoel)

- **R1 · Geen Filament-theme-laag.** `AdminPanelProvider` injecteert alleen
  `--at-*` vars + `primary`. Die worden gebruikt door handgemaakte Blade-kaarten,
  maar **niet** door Filament's eigen chrome (sidebar/topbar/tabellen/badges/
  wizard/velden). Elk Filament-oppervlak is een licht eiland in een donkere pagina.
- **R2 · `target-rings` schalingsbug** (`target-rings.blade.php:74-75`, geen
  `*0.35` factor + geen crosshair/center-dot/ten-ring-halo) → de "hit pattern"
  ziet eruit als hagel i.p.v. een groep.
- **R3 · AI-stuk kapot/leeg.** `ShooterCoach.php:129,162` interpoleert de backed
  `Deviation`-enum als string → **runtime fatal**; en de prompt bevat geen
  numerieke schotdata, dus data-gegronde inzichten ("4e serie zakt naar 87.2")
  zijn onmogelijk.

## Beslissingen (afgestemd met gebruiker 2026-06-05)

1. **Filament-chrome theming** → **`make:filament-theme` + `->viteTheme()`**
   (gesanctioneerde Filament-weg). Voegt één npm/vite theme-entry toe —
   **expliciet goedgekeurd** (CLAUDE.md dep/build-akkoord ✓).
2. **AI-coach** → **floating Copilot-widget blijft chat-engine**; de center-kolom
   wordt een getrouwe preview/launcher (géén volledige in-page chat-thread).
3. **Nieuwe AI-features** (`TrainingGoal` + `ScoreDriftService`) → **nu meebouwen**
   in deze ronde (elk met eigen AC's, zie §G).
4. **Ronde-scope** → **fundament + top-fidelity** in één worktree/PR
   (`feature/design-fidelity`); diepere polish + volledige chat-thread +
   icon-set in vervolg-PR's.

## Data-availability (geverifieerd tegen DB-schema)

- **Aanwezig**: volledige schotdata (`session_shots.x/y_normalized, ring, score`),
  kalibratie (`korrel_correction, vizier_correction, trigger_weight_g, grip_size`),
  `sessions.range_name/location`, `weapons.serial_number/owned_since/is_active`,
  reflectie-shape (`summary/positives/improvements/next_focus`).
- **Ontbreekt → wordt weggelaten (niet gefaket)**: weer (temp/wind), wapengewicht,
  "laatste onderhoud".
- **Discipline** is geen kolom → **afgeleid** uit `weapon_type` + `distance_m`.
- **Nieuw schema nodig**: `ai_reflections.acknowledged_at` ("Markeer"),
  `training_goals` tabel (nieuwe feature).

---

## Acceptance criteria

- **AC1** — Filament-panel rendert in **Signal Mint dark** via een echte
  theme-CSS (viteTheme): sidebar (220px, `--at-panel`, accent active-border),
  topbar (56px), tabellen, badges, wizard-stepper en form-velden consumeren
  allemaal de `--at-*` tokens. Geen licht "Filament-eiland" meer.
- **AC2** — Navigatie gegroepeerd als **LOG / INZICHT / BEHEER** met mono-uppercase
  groeplabels, nav-badges (sessie- + wapen-count, `NEW` op AI-coach), en
  sidebar-footer (avatar + naam + club·licentie).
- **AC3** — `target-rings` toont een correcte, geschaalde groep met crosshair,
  center-dot en ten-ring-emphasis; pixel-close aan `shared.jsx TargetRings`.
- **AC4** — Sessies-overzicht: de "Recente sessies"-tabel is een dense Signal-Mint
  grid-kaart (7 kolommen, mono datum + `S-xxxx` sub, accent-score, AI/open-badge),
  én de **"Wapens · gebruik"** 3-up kaart met inline sparklines is aanwezig.
- **AC5** — Sessie-detail: EINDSCORE-delta (`▲ +5 vs gem.` accent), 4-span
  header-meta (baan, wapen, tijd-window, weglaten-indien-leeg), X/Y/SD-rij onder
  hit-patroon, en de AI-reflectie-kaart met **"Vraag coach"** + **"Markeer"** knoppen.
- **AC6** — AI-backend: de `Deviation`-enum-crash is weg (regressietest), en de
  reflectie-prompt bevat numerieke schotdata (serie-scores, dip-range, tienen/negens,
  groep-mm, cadans) — bewezen via een prompt-content test.
- **AC7** — `AiWeaponInsight` (summary/patterns/suggestions) wordt op de wapen-detail
  getoond in een T3 BracketFrame-kaart (niet alleen via chat).
- **AC8** — `TrainingGoal`-feature: model + migratie + service + Copilot-tool +
  UI (voorgestelde-doelen-rail + "Voeg doel toe"-CTA), schutter-gescoped.
- **AC9** — `ScoreDriftService`: per-schot drift-aggregatie over laatste N
  matchende sessies (Eloquent), getoond als warn-sparkline; Copilot-tool eroverheen.
- **AC10** — Pest dekt alle nieuwe/gewijzigde code (≥90%); Pint clean;
  `php artisan test --compact` groen vóór elke commit.
- **AC11** — Issue #82 bevat een expliciete **"AI-scope (het AI-stuk)"**-sectie die
  overeenkomt met de design-belofte (zie §I); geen `DIVERGENT FROM JIRA`.

---

## Workstreams & stappen (per stap: build → test → Pint → sign-off → commit)

### A · Fundament (eerst — unlockt alle schermen)
- **A1 (R1)** — `php artisan make:filament-theme`, registreer via
  `->viteTheme('resources/css/filament/admin/theme.css')` op `AdminPanelProvider`,
  forceer dark. Map `fi-sidebar`/`fi-topbar`/`fi-main`/`fi-ta`/`fi-badge`/
  `fi-wizard`/`fi-input` op `--at-*`. Numerieke kolommen → `--at-font-mono`.
- **A2 (R2)** — Fix `target-rings.blade.php`: hit-scaling (`*0.35`, prop `hitScale`),
  crosshair-lijnen, center-dot, ten-ring-halo, ring-emphasis (10-ring 0.55 / overig 0.22).
- **A3** — Nav-groepen LOG/INZICHT/BEHEER (`->navigationGroups`), active-item
  styling (2px accent border-left), `getNavigationBadge()` op Session/Weapon/Coach,
  sidebar-header (wordmark + `v3.2 · self-hosted`) + footer (render hooks).
- **A4** — Topbar: mono-breadcrumb + gestylde search + ⌘K-chip.
- **A5** — `sparkline.blade.php`: echte `<linearGradient>` fill + terminal-dot.

### B · Sessies-overzicht
- **B1** — Vervang `{{ $this->table }}` door dense Blade-grid-kaart;
  `RangeConsoleSummaryService::recentSessions()` (Eloquent eager-load).
- **B2** — Voeg "Wapens · gebruik" 3-up kaart toe; `::weaponUsage()` aggregatie.
- **B3** — Rail 340→320px; AI-kaart `✦`→accent AI-icoon + "Vraag AI-coach iets"-knop;
  stat-card delta-pijl-prop.

### C · Sessie-detail
- **C1** — EINDSCORE-delta `scoreVsAverage()` (accent/warn).
- **C2** — 4-span header-meta (baan/wapen/tijd-window via eerste-laatste schot-`created_at`/weglaten-indien-leeg).
- **C3** — X/Y/SD-rij: `SessionStatsService::meanXmm()/meanYmm()/sdMm()`.
- **C4** — AI-reflectie-knoppen "Vraag coach" (Copilot pre-seed) + "Markeer"
  (`acknowledged_at`); AI-icoon + `gegenereerd HH:i`; dip-getal in warn-mono (XSS-safe).
- **C5** — Card-head accent-iconen; rechter-kolom 360→340px; shot-strip-legend `concentratiedip`.

### D · Wapen-detail (dichtst bij — lichte polish)
- **D1** — Sessies-tabel 5→6 kolommen (Discipline·Baan twee-regelig, trailing `⋯`).
- **D2** — `SERIAL · {serial_number}` subtitle; kv-rijen aligned (Status `· WM-4`,
  Aangeschaft, Kaliber + alleen bestaande velden).
- **D3** — Trend-as 6 kwartaal-ticks; stat-subs (`+N/mnd`, `▲ +x.x`).
- **D4 (AC7)** — `AiWeaponInsight` in T3 BracketFrame-kaart.

### E · AI-coach (launcher-variant, decision 2)
- **E1** — Center-kolom: getrouwe preview/launcher (geen volledige thread).
  Suggestie-chips → echte `<button>`s die `copilot-open` dispatchen.
- **E2** — Voorgestelde-doelen-rail (uit §G TrainingGoal) + privacy-note.

### F · AI-backend bugfixes (R3 — blocker)
- **F1 (AC6)** — `$entry->deviation?->value ?? 'n.v.t.'` op `ShooterCoach.php:129,162`
  + regressietest (reflectie voor sessie met non-null Deviation → geen exception).
- **F2 (AC6)** — Injecteer `SessionStatsService`-numeriek in `buildSessionPrompt`
  (+ `GenerateSessionReflectionJob`); prompt-content test.

### G · Nieuwe AI-features (decision 3 — eigen AC's)
- **G1 (AC8)** — `TrainingGoal`: model + migratie (`user_id, title, detail,
  source[ai|manual], status, target_month, session_id?, weapon_id?`) + factory +
  `TrainingGoalService` + Copilot `SuggestTrainingGoalTool`/`AddTrainingGoalTool` +
  Filament-UI (rail + CTA). Schutter-gescoped. Tests.
- **G2 (AC9)** — `ScoreDriftService` (Eloquent per-schot-drift over laatste N
  matchende sessies) + Copilot-tool + warn-sparkline render. Tests.

### H · Kwaliteit
- Pest per stap (`--filter`), daarna volledige suite. Pint + `php -l`.
- Pest 4 browser-smoke per Range-Console-scherm (`assertNoJavascriptErrors`).
- Architectuur-checks (Service voor logica, geen Model-queries in pages, Eloquent-only).

### I · Issue #82 bijwerken (AC11 — vóór BUILD, rule #10)
Voeg een **"AI-scope (het AI-stuk)"**-sectie toe aan #82 met:
1. De 2 AI-blockers als AC-regels (enum-crash, prompt-data).
2. De ontbrekende AI-features (training-goals, score-drift, reflectie-acties +
   `acknowledged_at`, weapon-insight surfacing).
3. De architectuur-beslissing coach (launcher i.p.v. in-page thread) als ADR-ref.
4. Bevestiging dat de AI-scope overeenkomt met de design-belofte.

---

## Niet in deze ronde (vervolg-PR's)
- Volledige in-page chat-thread (decision 2 = launcher).
- Wizard-numpad-overhaul / modal-conversie (blijft preview; alleen chrome donker).
- Eigen icon-set (heroicons blijven voorlopig).
- Weer/wapengewicht/onderhoud-velden (ontbreken in model).
- Fase 3 (marketing) & Fase 4 (mobile).

## STOP-gates (CLAUDE.md)
- PLAN moet **APPROVED** zijn vóór BUILD (deze sign-off).
- Issue #82 AI-scope bijwerken vóór BUILD (rule #10).
- Coverage <90% op gewijzigde code → STOP.
- Commit pas na expliciete goedkeuring per stap.

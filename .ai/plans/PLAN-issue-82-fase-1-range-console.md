---
status: APPROVED
created: 2026-05-15
updated: 2026-05-15
approved: 2026-05-15 (Marcvdc)
author: Claude (Opus 4.7)
jira: GH#82
worktree: aimtrack-range-console (feature/range-console)
basis: e809582 (main, post-PR #86 merge)
---

# PLAN: Issue #82 · Fase 1 — Range Console (5 kernschermen)

## Status: COMPLETED

BUILD afgerond. Alle 5 kernschermen geland op `feature/range-console`,
163 tests groen (3× stabiel), Pint clean.

### Geland (commits op feature/range-console)
1. `f4a77d1` — shared components (page-header, stat-card, target-rings,
   shot-strip, series-card, ai-reflection-card) + SessionStatsService +
   WeaponInsightsService.
2. `4ee2db7` — ListSessions redesign: KPI-strip + right-rail (target-rings,
   AI-reflectie, trend-sparkline) + RangeConsoleSummaryService + sparkline.
3. `b747e29` — ViewSession deep-dive: header (T1 + T4 stamp), 5 stats,
   series-card, shot-strip met dip-highlight, hit-pattern, AI-reflectie.
4. `a83d54f` — ViewWeapon: ID-kaart + kalibratie (migratie 4 velden) +
   KPI's + score-trend + sessies-tabel.
5. `b1b22ee` — AI-coach 3-koloms view (hybride: Copilot-state + eigen Blade)
   + CoachContextService.
6. `a0000a4` — nieuwe-sessie wizard (4-staps Filament v5 Wizard) +
   form-refactor naar herbruikbare static field-groups.
7. `c22c2bb` — fixes uit adversariële branch-review (N+1, scoping, stats).

### Afwijkingen / beslissingen tijdens BUILD
- **Schoten-stap wizard** is een preview (numpad/readout read-only) i.p.v.
  live loggen: het echte SessionShotBoard vereist een opgeslagen sessie en
  logt via target-click → ring/score (niet de decimaal-numpad uit het
  ontwerp). `getRedirectUrl()` stuurt na afronden naar het schotenbord.
- **AI-coach** is hybride (decision 4): de bestaande FilamentCopilot-widget
  blijft de chat-engine; de 3-koloms layout is eigen Blade.
- **Theme-switch (AC4 uit issue)** blijft uit scope — Signal Mint dark enige
  palet (afgesproken in Fase 0).
- Twee adversariële review-workflows (wizard + branch-breed) uitgevoerd;
  16 findings bevestigd en opgelost, incl. een latente Carbon-3 bug in
  avgCadansSec (gaf altijd null in productie).

### Niet in deze fase
- Empty states (Fase 2 · aparte worktree), mobile (Fase 4), marketing (Fase 3).

---

## (oorspronkelijk PLAN — APPROVED 2026-05-15)

Per stap commit + sign-off; pas door naar volgende stap na expliciete OK.

## Beslissingen na review (2026-05-15)

1. **Scope per scherm** → **pixel-close**: alles uit design tonen,
   ontbrekende data = placeholder `—`. Veld-pollutie geaccepteerd; design-
   balans is leidend.
2. **Series-card** → **dynamisch**: aantal series afgeleid van
   `session.discipline` (LP10 = 6×10, vrije sessies = `total_shots / 10`).
   `SessionStatsService::seriesScores($session)` rekent dit zelf uit.
3. **Kalibratie-velden** → **migratie + form-velden** voor
   `korrel_correction`, `vizier_correction`, `trigger_weight_g`,
   `grip_size` op `weapons`-tabel. Volledige feature, niet alleen visueel.
4. **AI-coach chat** → **hybride**: FilamentCopilot levert message-state +
   tool-execution, eigen Blade-DOM rendert de 3-koloms layout. Vereist
   exposure van Copilot's chat-state via een Livewire-property of
   render-hook — onderzocht in stap 5.
5. **Nieuwe-sessie wizard** → **Filament Wizard component (in-page)** met
   custom CSS om dichter bij het design te komen. Geen modal-overlay.
6. **Live AI-tip wizard-stap 3** → **hard-coded mock-tekst**. Geen
   Copilot-call. Box toont design-voorbeeld; functionaliteit voor latere
   iteratie.
7. **Sessies-overzicht zoekbalk** → **Filament global search skin**:
   bestaande search-bar via custom CSS dichter bij design (search-icon,
   monospace placeholder, ⌘K-chip).
8. **PR-strategie** → **1 PR met alle 5 schermen**. Per stap commit +
   sign-off (zoals afgesproken in Fase 2 PLAN).

## Samenvatting

Vijf kernschermen van het Filament-panel herontwerpen in het Signal Mint
dark design uit Fase 0, op basis van de Range Console-richting (variation
A) uit `.ai/design-handoff/project/`:

1. **Sessies-overzicht** — `SessionResource` index-pagina krijgt KPI-strip,
   page-header met T1 watermark, geherontworpen tabel en een rechter-rail
   met "Laatste sessie" target-rings + AI-reflectie (T3 BracketFrame) +
   30d trend-spark. (`range-console.jsx`)
2. **Sessie-detail** — `SessionResource` view-pagina met header-card
   (T1 watermark + T4 "WM-4 OK" stamp), 5-cell stats-strip, series-card
   (6×10), shot-strip (60 bars), hit-pattern target-rings, T3
   AI-reflectie en eigen-notitie. (`a-screens.jsx · SessionDetail`)
3. **Wapen-detail** — `WeaponResource` view-pagina met links een
   identiteits-card (wapen-silhouet, status-rijen, kalibratie-blok) en
   rechts 4 KPI's + lange score-trend + sessies-met-dit-wapen tabel.
   (`a-screens.jsx · WeaponDetail`)
4. **AI-coach view** — `CoachPage` blade-view krijgt 3-koloms layout:
   gesprekken-rail links, chat-thread centraal met composer + quick-reply
   chips, context-rail rechts (actieve sessies/wapens/doelen).
   (`a-screens.jsx · AICoachView`)
5. **Nieuwe-sessie wizard** — Stap-voor-stap flow voor `CreateSession`:
   Wapen → Sessie → Schoten → Notities. Live "VOORTGANG / LOPENDE SCORE /
   GEM. PER SCHOT" readout, score-numpad of handmatige invoer, context-rail
   met sessie/wapen/opties. (`new-session-wizard.jsx`)

Alle 5 hergebruiken de Fase 0 Blade-componenten (`<x-aimtrack.reticle>`,
`<x-aimtrack.watermark-bg>`, `<x-aimtrack.bracket-frame>`,
`<x-aimtrack.monogram-stamp>`, `<x-aimtrack.wordmark>`) en de
`aimtrack-tokens.css` variabelen.

**Niet in scope:** Empty states (Fase 2), mobile companion (Fase 4),
marketing landing (Fase 3), Tactical-HUD en Field-Journal varianten
(alternatieve richtingen, expliciet uit issue gehaald).

## Motivatie

- De 5 kernschermen vormen samen 80% van de gebruikerstijd in de app —
  herontwerp hier levert de meeste perceived-quality lift.
- Fase 0 heeft alle tokens + logo-componenten geland zonder afnemers; deze
  fase activeert ze in echte UI.
- AC3 uit issue #82 stelt expliciet: "Sessie-detail toont schot-voor-schot
  strip, hit-pattern target-rings en T3-bracketed AI-reflectie panel;
  pixel-close aan `range-console.jsx` SessionDetail."
- De nieuwe-sessie wizard maakt de hoofdactie (sessie loggen) sneller dan
  de huidige lange InfoSection-form — en is de plek waar gebruikers
  vandaag de meeste friction ervaren.

## Acceptatiecriteria

1. **AC1** — `<x-aimtrack.page-header>` Blade-component met slot voor
   `title`, `subtitle`, `actions` + ingebouwde T1 watermark (positie en
   opacity configureerbaar). Wordt gebruikt door AC2, AC4 en AC6.
2. **AC2** — `ListSessions` rendert: page-header, 4 KPI-widgets
   (Sessies/mnd, Schoten totaal, Beste serie, AI-reflecties), de bestaande
   tabel met aangepaste kolommen + statusbadges (AI ⇢ groen, open ⇢ amber),
   en een rechter-rail met `LastSessionPreviewWidget` (target-rings + score
   readouts) + `LatestReflectionWidget` (T3 BracketFrame) +
   `Trend30dWidget` (sparkline). Visueel pixel-close aan
   `range-console.jsx`.
3. **AC3** — `ViewSession` toont: header-card (T1 watermark + T4
   monogram-stamp "WM-4 OK") met datum/discipline/score; 5-cell stats-row
   (Beste schot, Tienen, Negens, Groep, Gem. cadans); series-card
   (6 × 10-schoten balken met `accent` als ≥95); shot-strip met 60 bars
   (groen ≥10, amber bij concentratie-dip, grijs normaal); hit-pattern
   target-rings; T3 AI-reflectie-panel met STERK/VERBETER/VOLGENDE rijen;
   eigen-notitie kaart. **Vereist `SessionShot`-data** (al beschikbaar).
   Kolommen `tienen`, `negens`, `groep_mm`, `cadans_sec` kunnen on-the-fly
   afgeleid worden uit shots — geen migratie nodig.
4. **AC4** — `ViewWeapon` toont: links een ID-kaart (foto-placeholder
   SVG-silhouet, naam, type, kaliber, serial, KALIBRATIE-blok met
   korrel/vizier/trekker/handgreep); rechts 4-cell KPI's + score-trend
   spark + tabel "Sessies met dit wapen". **Open punt 3** beslist of de
   kalibratie-velden in DB worden opgeslagen of read-only zonder
   persistence (uit `notes` parsing of helemaal weggelaten).
5. **AC5** — `CoachPage` rendert in een 3-koloms layout: chat-list rail
   (recente gesprekken), centrale chat-thread met Copilot-integratie en
   composer + quick-reply chips, context-rail (actieve sessies/wapens +
   voorgestelde doelen + privacy-notice). **Open punt 4** beslist of we
   FilamentCopilot's chat-widget integreren of een eigen Livewire-chat
   bouwen.
6. **AC6** — `CreateSession` route gebruikt een Filament Wizard met 4
   stappen (Wapen, Sessie, Schoten, Notities). Stap "Schoten" toont een
   live VOORTGANG/LOPENDE SCORE/GEM-readout, een 60-slot shot-strip
   (filled/placeholder), een score-numpad (10.9 … 8 … 0) of handmatige
   invoer, en een context-rail met sessie/wapen + AI-tijdens-sessie tip.
   **Open punt 5** beslist of dit een modal-overlay (zoals design) of een
   in-page wizard (Filament-default) wordt.
7. **AC7** — De AI-reflectie card in AC2 en AC3 hergebruikt dezelfde
   `<x-aimtrack.ai-reflection-card>` component met props
   `reflection`, `compact?` en `actions[]`.
8. **AC8** — Target-rings (`<x-aimtrack.target-rings>`) en shot-strip
   (`<x-aimtrack.shot-strip>`) zijn herbruikbare Blade-componenten met
   geconfigureerde grootte, kleuren via tokens, hits-array prop.
9. **AC9** — Per scherm minimaal 3 Pest-tests:
   - Smoke: pagina rendert zonder errors voor user met data
   - Empty: pagina rendert ook zonder data (voor sessie-detail: stub)
   - Assertief op aanwezigheid van design-elementen (`assertSee('LP10')`,
     `assertSeeBlade('x-aimtrack.bracket-frame')`)

   Plus component-tests per nieuwe `<x-aimtrack.*>` component.
   Totaal: ≥20 nieuwe tests.
10. **AC10** — `vendor/bin/pint --dirty` clean, `php artisan test --compact`
    groen. Coverage op nieuwe code ≥90%.
11. **AC11** — Manuele rondgang in dev-stack (`http://localhost:19087`)
    door alle 5 schermen zonder JS-errors of console-warnings.
12. **AC12** — Architectuur: nieuwe afgeleide stats voor SessionShot
    (Tienen/Negens/Groep/Cadans) gaan via een dedicated
    `SessionStatsService` of Eloquent-accessor — **geen** business-logica
    in Blade-views of Filament Resources (CLAUDE.md rule 11/12).

## Buiten scope

- Migraties voor nieuwe kolommen (kalibratie, etc.) tenzij **open punt 3**
  zegt anders.
- Empty-states voor de 5 nieuwe schermen — Fase 2.
- AI-coach prompt-engineering of nieuwe Copilot-tools — gebruik wat er is.
- Onderhouds-loggen op WeaponDetail (CTA-button uit design) — alleen
  visueel, geen functie. Toekomstige iteratie.
- Match-routine planner / trainingsdoelen-tabel uit AI-coach
  context-rail — UI placeholder, geen achterliggende tabel.

## Afhankelijkheden & risico's

| Risico | Impact | Mitigatie |
|---|---|---|
| Wizard-overlay (modal) past niet binnen Filament v5 page-structure | Hoog | **Open punt 5** — keuze tussen in-page Wizard (Filament-default, makkelijk) of custom Livewire modal (matched design beter, meer werk). PLAN biedt beide paden. |
| FilamentCopilot's chat-widget niet hergebruikbaar als 3-koloms-embed | Medium | **Open punt 4** — fallback is custom Livewire chat. CoachPage werkt vandaag, dus we kunnen 3-koloms layout om de bestaande widget heen bouwen. |
| SessionShot-data niet betrouwbaar genoeg voor afgeleide stats | Medium | Stats die niet berekenbaar zijn tonen `—` placeholder. Smoke-test asserts dat de stats-cells altijd renderen. |
| Kalibratie-velden ontbreken op `Weapon`-model | Laag | **Open punt 3** — keuze tussen migratie of read-only weergeven. PLAN geeft drie paden. |
| Range Console list-view "Wapens-gebruik" card vergt aggregate query per request | Laag | Cache per-user voor 5 min via `Cache::remember()` keyed op `weapons-usage:{user_id}`. Invalidate via SessionShot-observer. |
| Performance van page-header T1 watermark op elke pagina | Laag | T1 is inline SVG, geen runtime cost. Al gebenchmarked in Fase 0. |
| 5 schermen in 1 PR = grote diff, slechte reviewbaar | Hoog | **Open punt 8** — keuze: alles in 1 PR óf split in 2-3 sub-PR's per scherm-cluster (zie fasering). |

## Architectuur

### Bestandsboom (na BUILD)

```
resources/views/components/aimtrack/                # bestaand (Fase 0)
├── page-header.blade.php                           # NIEUW · AC1
├── target-rings.blade.php                          # NIEUW · AC8
├── shot-strip.blade.php                            # NIEUW · AC8
├── series-card.blade.php                           # NIEUW · 6×10 balken
├── stat-card.blade.php                             # NIEUW · KPI-cel
├── ai-reflection-card.blade.php                    # NIEUW · AC7 (T3 wrap)
└── (bestaande Fase 0 componenten — ongewijzigd)

resources/views/filament/
├── pages/coach-page.blade.php                      # WIJZIG · 3-koloms layout
├── resources/sessions/
│   ├── view-session.blade.php                      # NIEUW · custom view
│   └── session-shot-board-panel.blade.php          # ONGEWIJZIGD
└── resources/weapons/
    └── view-weapon.blade.php                       # NIEUW · custom view

app/Filament/
├── Pages/CoachPage.php                             # WIJZIG · context bindings
├── Resources/SessionResource.php                   # WIJZIG · table tweaks
├── Resources/SessionResource/Pages/
│   ├── ListSessions.php                            # WIJZIG · widgets + header
│   ├── ViewSession.php                             # WIJZIG · custom view
│   └── CreateSession.php                           # WIJZIG · Wizard wrap
├── Resources/WeaponResource.php                    # WIJZIG · table tweaks
├── Resources/WeaponResource/Pages/
│   └── ViewWeapon.php                              # WIJZIG · custom view
└── Widgets/
    ├── LastSessionPreviewWidget.php                # NIEUW · target-rings
    ├── LatestReflectionWidget.php                  # NIEUW · T3 card
    ├── Trend30dWidget.php                          # NIEUW · sparkline
    └── SessionsKpiWidget.php                       # NIEUW · 4 KPI's

app/Services/                                       # nieuw (architectuur-rule)
├── SessionStatsService.php                         # NIEUW · tienen/negens/groep/cadans
└── WeaponInsightsService.php                       # NIEUW · 4 KPI's + trend-data

tests/Feature/RangeConsole/
├── ListSessionsScreenTest.php                      # NIEUW · AC2 + AC9
├── ViewSessionScreenTest.php                       # NIEUW · AC3 + AC9
├── ViewWeaponScreenTest.php                        # NIEUW · AC4 + AC9
├── AiCoachScreenTest.php                           # NIEUW · AC5 + AC9
└── NewSessionWizardTest.php                        # NIEUW · AC6 + AC9
tests/Unit/Aimtrack/
├── PageHeaderComponentTest.php                     # NIEUW · AC1
├── TargetRingsComponentTest.php                    # NIEUW · AC8
├── ShotStripComponentTest.php                      # NIEUW · AC8
└── AiReflectionCardComponentTest.php               # NIEUW · AC7
tests/Unit/Services/
├── SessionStatsServiceTest.php                     # NIEUW · AC12
└── WeaponInsightsServiceTest.php                   # NIEUW · AC12
```

### Architectuur-lagen (CLAUDE.md rules 11/12)

```
[Filament Page]  ──reads──>  [Service]  ──reads──>  [Model::query()]
   (view binding)              (stats logica)         (Eloquent only)
        │
        └──renders──> [Blade component <x-aimtrack.*>]
                         (presentation only,
                          props in / DOM out)
```

Geen `DB::`, geen business-logica in Resources of Blade.

### Component-API (3 voorbeelden)

```blade
{{-- AC1 --}}
<x-aimtrack.page-header
    :reticle-opacity="0.07"
    :reticle-size="180"
    title="Sessies"
    subtitle="Overzicht van je trainingen · 247 totaal"
>
    <x-slot:actions>
        <a href="…" class="at-btn">Filter</a>
        <a href="…" class="at-btn-prim">Nieuwe sessie</a>
    </x-slot:actions>
</x-aimtrack.page-header>

{{-- AC8 --}}
<x-aimtrack.shot-strip
    :shots="$session->shots->pluck('score')"
    :total-slots="60"
    :dip-range="[33, 42]"  {{-- optioneel, auto-detect via stats-service --}}
/>

{{-- AC7 --}}
<x-aimtrack.ai-reflection-card :reflection="$session->aiReflection" :session-id="$session->id">
    <x-slot:actions>
        <button class="at-btn-ghost">Vraag coach</button>
        <button class="at-btn">Markeer</button>
    </x-slot:actions>
</x-aimtrack.ai-reflection-card>
```

### SessionStatsService (architectuur-voorbeeld)

```php
final class SessionStatsService
{
    public function __construct(private readonly Session $session) {}

    public function tienen(): int { /* count shots ≥10.0 */ }
    public function negens(): int { /* count 9.0–9.9 */ }
    public function groupMm(): ?float { /* SD-based group calc */ }
    public function avgCadansSec(): ?float { /* time-delta between shots */ }
    public function seriesScores(int $perSerie = 10): array { /* 6×10 sums */ }
    public function dipRange(): ?array { /* [from, to] van laagste reeks */ }
}
```

## Fasering

Voorgestelde fasering — gefaseerd commits + sign-off per stap (per
afspraak in Fase 2 PLAN).

### Stap 1 — Shared components + services
- [ ] `<x-aimtrack.page-header>` + test
- [ ] `<x-aimtrack.target-rings>` + test
- [ ] `<x-aimtrack.shot-strip>` + test
- [ ] `<x-aimtrack.series-card>` + test
- [ ] `<x-aimtrack.stat-card>` + test
- [ ] `<x-aimtrack.ai-reflection-card>` + test
- [ ] `SessionStatsService` + test
- [ ] `WeaponInsightsService` + test
- [ ] Pint clean

### Stap 2 — Sessies-overzicht (ListSessions)
- [ ] 4 KPI-widgets
- [ ] Page-header met T1
- [ ] Tabel-tweaks (statusbadges, kolom-volgorde)
- [ ] Rechter-rail widgets (LastSession + LatestReflection + Trend30d)
- [ ] AC2 + AC9 tests

### Stap 3 — Sessie-detail (ViewSession)
- [ ] Custom view-template
- [ ] Header-card (T1 + T4)
- [ ] Stats-row, series-card, shot-strip
- [ ] Hit-pattern target-rings
- [ ] T3 AI-reflectie + eigen-notitie
- [ ] AC3 + AC9 tests

### Stap 4 — Wapen-detail (ViewWeapon)
- [ ] Custom view-template
- [ ] ID-kaart links (foto-silhouet + status-rijen + kalibratie)
- [ ] KPI's + lange trend + sessies-tabel rechts
- [ ] AC4 + AC9 tests

### Stap 5 — AI-coach view (CoachPage)
- [ ] 3-koloms layout
- [ ] Chat-list rail
- [ ] Copilot-chat-embed of custom Livewire-chat (per **open punt 4**)
- [ ] Context-rail rechts
- [ ] AC5 + AC9 tests

### Stap 6 — Nieuwe-sessie wizard (CreateSession)
- [ ] Filament Wizard óf custom modal (per **open punt 5**)
- [ ] 4 stappen: Wapen / Sessie / Schoten / Notities
- [ ] Live VOORTGANG/LOPENDE SCORE readout op stap 3
- [ ] Score-numpad of handmatige invoer
- [ ] AC6 + AC9 tests

### Stap 7 — Smoke + Pint + PR
- [ ] `vendor/bin/pint --dirty`
- [ ] `php artisan test --compact` → groen
- [ ] Manuele browser-check op http://localhost:19087
- [ ] `gh pr create` per **open punt 8** (1 PR of 3 sub-PR's)

### Commits-cadans
Per stap één commit + sign-off. Voorgestelde commit-titels:
1. `feat(range-console): shared components + stats/insights services`
2. `feat(range-console): ListSessions redesign with KPIs + right rail`
3. `feat(range-console): ViewSession deep-dive with shot-strip + hit pattern`
4. `feat(range-console): ViewWeapon identity card + trend + sessions`
5. `feat(range-console): AI-coach 3-column view with context rail`
6. `feat(range-console): new-session wizard with live shot logging`
7. `test(range-console): feature + component coverage`

PR-titel (single-PR optie): `feat(range-console): 5 core screens (#82 fase 1)`

## Open punten

Alle 8 punten beantwoord — zie "Beslissingen na review" bovenaan. De
oorspronkelijke vragen blijven hieronder voor traceability.

### Oorspronkelijke vragen (afgesloten)

1. **Scope per scherm: pixel-close of pragmatic?** Het design toont
   "Walther LP500 4.5 mm", "WM-4 OK", "32 minuten · 17 °C · vlak" en
   meer detail-velden die niet allemaal in het huidige Session/Weapon-model
   bestaan. Optie A: alles tonen, ontbrekende velden = placeholder `—`.
   Optie B: alleen design-elementen tonen waarvoor data bestaat.
2. **Series-card: hard-coded 6×10 of dynamisch?** Discipline LP10 doet
   60 schoten in 6 series van 10. Vrije sessies hebben willekeurige aantallen.
   Optie A: hard 6×10. Optie B: afgeleid van `session.discipline` of
   `total_shots / 10`. Optie C: weglaten bij sessies zonder structuur.
3. **Kalibratie-velden Walther** (korrel/vizier/trekker/handgreep). Opties:
   (a) Migratie + form-velden in CreateWeapon (echte feature),
   (b) JSON-blob in `weapon.notes` (geen migratie, parse op render),
   (c) Read-only placeholders zonder persistence (visueel correct, geen data).
4. **AI-coach chat-implementatie**:
   (a) FilamentCopilot's eigen chat-widget embedded in 3-koloms layout
       (snelste, maar widget-styling beperkt te overriden),
   (b) Custom Livewire-chat met Copilot-API als backend (volle controle,
       meer code), (c) Hybride: Copilot voor messages-state, eigen Blade-DOM
       voor rendering.
5. **Nieuwe-sessie wizard layout**:
   (a) Filament Wizard component op standaard `CreateSession` page
       (in-page stepper, geen modal-overlay), (b) Custom Livewire-modal
       die over het dim-bare panel laadt (matches design pixel-close,
       meer werk), (c) Filament v5's built-in Wizard met custom CSS om de
       header/footer te stijlen tot dichtbij het design.
6. **Live AI-tijdens-sessie tip** op wizard-stap 3 (groene rand-box rechts
   onderin). Optie A: hard-coded mock-tekst (visueel). Optie B: real-time
   Copilot-call na elke 10 schoten (kost geld + latency). C: weglaten.
7. **Sessies-overzicht zoekbalk + ⌘K** uit design — Filament heeft eigen
   global search. Hergebruiken of UI-only nabouwen?
8. **PR-split**: alles in 1 PR (groot maar coherent) of split in:
   - PR-A: shared components + ListSessions + ViewSession
   - PR-B: ViewWeapon + AI-coach
   - PR-C: New-session wizard
   PR-B en PR-C kunnen parallel als shared components in PR-A landen.

---

**Verzoek**: beantwoord de 8 open punten. Daarna zet ik status op
`READY_FOR_APPROVAL`. Pas na expliciete "approved" begin ik met BUILD-stap 1.

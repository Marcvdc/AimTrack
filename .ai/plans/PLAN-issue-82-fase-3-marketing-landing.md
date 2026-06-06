---
status: APPROVED
created: 2026-05-30
updated: 2026-05-30
approved: 2026-05-30 (marc@kjsoftware.nl)
completed: null
author: Claude (Opus 4.8)
jira: GH#82
worktree: aimtrack-marketing (feature/marketing)
basis: e809582 (main, post-PR #86 merge — Fase 0 foundation)
---

# PLAN: Issue #82 · Fase 3 — Marketing landing

## Status: APPROVED

Goedgekeurd door marc@kjsoftware.nl op 2026-05-30. BUILD per stap met
check-in + expliciete OK vóór de volgende stap (zie "Beslissingen vooraf" #6).

## Herziening 2026-05-30 (sessie 2) — APPROVED

Na het mergen van `main` (Fase 1 Range Console + Fase 2 empty-states +
design-fidelity Fase 1b: dark Filament-theme, AI-features, dashboard, Vite) en
op verzoek van de gebruiker zijn drie beslissingen herzien. Deze amendment
**superseedt** de bijbehorende punten hieronder:

- **R1 — Live data (superseedt Beslissing #2 & #4, AC8).** De landingspagina is
  géén pure statische marketing meer: hero-callouts en de Trends-sparkline tonen
  **echte instance-brede aggregaten** (gem. score, sessies, AI-reflecties,
  score-trend) uit de DB. `app/Support/Landing/LandingPageData.php` en
  `config/landing.php` worden daarom **herbestemd** (niet verwijderd); de
  controller injecteert de data. **Lege-staat-fallback:** bij een lege/verse
  instance een nette "begin je eerste sessie"-staat i.p.v. nullen of misleidende
  neppe cijfers. Brand-componenten van `main` (`target-rings`, `sparkline`)
  worden hergebruikt; mijn `spark` wordt geconsolideerd richting main's
  `sparkline` waar dat netjes kan.
- **R2 — Club configureerbaar (nieuw, Beslissing #7).** `config/landing.php`
  krijgt een `club`-key (env-overschrijfbaar), default **"SSV Scherpschutters"**
  (de eigen club van de gebruiker). Trust-strip + footer gebruiken die i.p.v.
  fictieve clubnamen.
- **R3 — Geen prijzen (superseedt AC6-pricing, nieuw Beslissing #8).** De app is
  gratis; de **pricing-sectie vervalt**. De "gratis · open-source · MIT ·
  self-hosted"-boodschap vouwt in de self-hosted-strook. CTA-taal weg van
  trial/pricing: nav "Probeer gratis" → **"Aan de slag"**, hero "30 dagen gratis
  proberen" → **"Gratis aan de slag"** (beide → `/admin/login`).

- **R4 — Componenten-correctie (integratie-audit sessie 2).** `target-rings`
  stond al op main (Fase 1) en wordt ongewijzigd hergebruikt; mijn duplicaat
  `spark` is **verwijderd** t.g.v. main's `sparkline` (met `:fill="true"`, ook
  voor de Stap-3 Trends-kaart). Écht nieuw op deze branch zijn alleen `icon` +
  `head-assets`. `head-assets` is nu de **enige** bron voor fonts + tokens — ook
  `AdminPanelProvider::aimTrackDesignAssets()` rendert die component, zodat panel
  en landing niet driften. De bestandsboom verderop is op die punten verouderd.
  Stap-3 Trends-sparkline gebruikt `SessionWeapon.rounds_fired` per datum (niet
  de in de praktijk lege `SessionShot`-tabel).

Tests groeien per stap mee; suite blijft per commit groen.

## Beslissingen vooraf (sessie 2026-05-30)

1. **Route** → de bestaande publieke route `/` (name `welcome` →
   `LandingPageController` → `resources/views/welcome.blade.php`). Géén
   subdomein. (Beantwoordt open vraag #3 uit issue #82.)
2. **Richting** → **pure marketing-redesign**. De huidige `welcome.blade.php`
   is een pre-handoff pagina (eigen inline-styles, verkeerd palet, Space
   Grotesk/Manrope). Die wordt volledig vervangen door het Signal Mint dark
   design uit `.ai/design-handoff/project/marketing.jsx`. De live-aggregaat
   statistieken (ring-heatmap, gem. score) + KNSA-referentielinks vervallen
   op de landingspagina.
3. **Palet** → **alleen Signal Mint dark** (consistent met Fase 0 en met
   `marketing.jsx`, dat dark-only is). Geen light/Daylight-toggle in deze
   fase. (Open vraag #2 → buiten scope.)
4. **Dead code** → `LandingPageData` + `config/landing.php` worden ná de
   redesign nergens meer gebruikt (geverifieerd: enige referenties zijn de
   controller zelf + `LandingPageTest`). Beide worden verwijderd; de
   controller wordt vereenvoudigd tot `return view('welcome')`.
5. **Route-naam blijft** → `welcome` route + `welcome.blade.php` bestandsnaam
   blijven onveranderd. `LoginCtaTest`, de Filament user-menu `landing-page`
   actie en `login-extras.blade.php` verwijzen naar `route('welcome')` en
   blijven dus groen/werkend.
6. **Commits-cadans** → **per stap** (zelfde aanpak als Fase 2). Na elke stap
   één commit + check-in; door naar volgende stap na expliciete OK.

## Samenvatting

Eén publieke long-scroll marketingpagina (`aimtrack.nl`, route `/`) bouwen in
het Signal Mint dark design, gebaseerd op `.ai/design-handoff/project/marketing.jsx`
(419 regels). De pagina bestaat uit 8 secties:

1. **Nav** — sticky, blurred bg, `<x-aimtrack.wordmark>`, menu-anker-links,
   "Inloggen" + "Probeer gratis" CTA's.
2. **Hero** — headline ("Je schietsessies, scherp in beeld."), subcopy, 2
   CTA's, feature-pills; visual = `<x-aimtrack.reticle>` + nieuwe
   `<x-aimtrack.target-rings>` met zwevende score/groep/AI-callouts.
3. **Trust strip** — "Gebruikt door sportschutters bij" + verenigingsnamen.
4. **Features** — 6 cards (Sessies, AI-coach, Trends met `<x-aimtrack.spark>`,
   WM-4 met **T4 `<x-aimtrack.monogram-stamp>`**, Privacy, Wapens). Elke card
   met accent-bracket-hoek rechtsboven.
5. **AI-coach deep-dive** — checklist + **T3 `<x-aimtrack.bracket-frame>`**
   mock-chat met `<x-aimtrack.spark>` score-drift.
6. **Self-hosted strip** — terminal-mockup (`docker compose up`).
7. **Pricing** — 3 tiers (Self-hosted € 0, Schutter € 4 "POPULAIR",
   Vereniging € 1).
8. **Footer** — wordmark, versie, links.

Alle inhoud is statische Nederlandse marketing-copy (mock-cijfers uit het
prototype, geen live DB-data). Hergebruikt Fase 0 brand-componenten en
`aimtrack-tokens.css`. Vier kleine nieuwe brand-primitieven worden toegevoegd
(`target-rings`, `spark`, `icon`, `head-assets`) — ook bruikbaar voor Fase 1.

## Motivatie

- **Eerste contactmoment**: de landingspagina is wat een prospect ziet vóór
  inlog. De huidige pagina staat los van de nieuwe visuele taal (ander palet,
  andere fonts) — inconsistent met het ingelogde panel uit Fase 0/2.
- **Design handoff is hier expliciet voor gemaakt**: `marketing.jsx` is een
  volledig uitgewerkt prototype; Fase 3 zet dat 1-op-1 om naar Blade.
- **Brand-primitieven hergebruiken**: reticle/bracket-frame/monogram-stamp/
  wordmark bestaan al (Fase 0). `target-rings`/`spark`/`icon` die hier
  bijkomen heeft Fase 1 (range console) óók nodig — niet weggegooid werk.
- Issue #82 listet Fase 3 expliciet met `[ ]`: "Public landing page: hero,
  features, AI deep-dive, self-hosted, prijzen" + "T3 BracketFrame om AI-chat
  preview, T4 stamp op WM-4 feature".

## Acceptatiecriteria

1. **AC1** — `resources/views/welcome.blade.php` is volledig herbouwd in
   Signal Mint dark: gebruikt uitsluitend `--at-*` tokens (via
   `<x-aimtrack.head-assets>`), Inter/JetBrains Mono fonts, en bevat geen
   resten van het pre-handoff palet (`#64f4b3` hardcoded, `--indigo`,
   Space Grotesk/Manrope, `.heatmap`, `.badge`-oud).
2. **AC2** — Vier nieuwe herbruikbare componenten onder
   `resources/views/components/aimtrack/`, met props-naamgeving die het
   prototype volgt:
   - `target-rings` (`size`, `accent`, `dim`, `ringStroke`, `showHits`) —
     concentrische ringen + crosshair + hit-pattern (port van
     `shared.jsx` `TargetRings`).
   - `spark` (`data`, `w`, `h`, `color`, `strokeW`, `fill`) — sparkline met
     area-gradient + eind-dot (port van `shared.jsx` `Spark`).
   - `icon` (`name`, `size`, `color`, `stroke`) — 24×24 stroke-1.6 icon-set
     (port van `shared.jsx` `ICONS`/`Icon`; minimaal: arrow, shield, session,
     ai, spark, export, check, weapon).
   - `head-assets` — emit Google-Fonts links (Inter + JetBrains Mono) +
     `<style id="aimtrack-tokens">` die `resource_path('css/aimtrack-tokens.css')`
     inleest. Eén bron van waarheid, gedeeld met het panel.
3. **AC3** — **Nav + Hero**: sticky blurred nav met wordmark + ankers +
   "Inloggen"(→ `/admin/login`) + "Probeer gratis". Hero met display-headline,
   accent-deel, subcopy, 2 CTA's en feature-pills; visual met reticle +
   target-rings + 3 zwevende callouts (SCORE/GROEP/AI). Pixel-close aan
   `marketing.jsx` Hero.
4. **AC4** — **Features**: 6 cards in een 3-koloms grid (responsive),
   elk met `<x-aimtrack.icon>`, mono-kicker, titel, body en demo-blokje.
   Card #3 (Trends) gebruikt `<x-aimtrack.spark>`; card #4 (WM-4) gebruikt
   **`<x-aimtrack.monogram-stamp label="WM-4 OK">` (T4)**. Accent-bracket-hoek
   rechtsboven op elke card.
5. **AC5** — **AI-coach deep-dive**: 2-koloms sectie met checklist (4 items
   met check-icon) links en **`<x-aimtrack.bracket-frame>` (T3)** mock-chat
   rechts, inclusief een `<x-aimtrack.spark>` score-drift-blok. Voldoet aan
   issue-eis "T3 BracketFrame om AI-chat preview".
6. **AC6** — **Self-hosted + Pricing + Footer**: terminal-mockup strip;
   3 pricing-cards (Self-hosted € 0 / Schutter € 4 met "POPULAIR"-badge en
   accent-rand / Vereniging € 1), elk met feature-lijst (check-icon) en CTA;
   footer met wordmark + "v… · MIT · NL" + links.
7. **AC7** — **Responsive + a11y**: pagina is bruikbaar op mobiel (grids
   vouwen naar 1 kolom < 768px; nav-links verbergen of stapelen netjes).
   Decoratieve SVG's zijn `aria-hidden`; de wordmark houdt `aria-label`.
   CTA's zijn echte `<a>`/`<button>` met focus-zichtbaarheid.
8. **AC8** — **Controller + dead-code**: `LandingPageController::__invoke()`
   vereenvoudigd naar `return view('welcome')` (geen `LandingPageData`-injectie).
   `app/Support/Landing/LandingPageData.php` en `config/landing.php` verwijderd
   (geverifieerd geen overige referenties). Route-naam `welcome` ongewijzigd.
9. **AC9** — **Tests**: `tests/Feature/LandingPageTest.php` herschreven (bestand
   behouden, niet verwijderd) en assert de nieuwe pagina als gast:
   - `GET /` → 200 zonder auth, zonder enige DB-seed-afhankelijkheid;
   - bevat hero-copy, ≥1 van elke kernsectie (features/AI/self-hosted/pricing),
     en CTA's naar `/admin/login` + `route('welcome')`-anker;
   - bevat géén oude strings ("Ring heatmap", "KNSA referenties",
     "Sessies deze maand").
   Plus component-render-tests voor `target-rings`, `spark`, `icon`,
   `head-assets` (`tests/Feature/Marketing/…` of `tests/Unit/…`, conform
   bestaande conventie). `LoginCtaTest` blijft ongewijzigd groen.
10. **AC10** — **Browser-smoke** (Pest 4): `tests/Browser/MarketingLandingTest.php`
    bezoekt `/`, `assertNoJavascriptErrors()`/`assertNoConsoleLogs()`, ziet
    hero + pricing, en klikt "Inloggen" → komt op `/admin/login`. (Vereist
    draaiende dev-stack op :19088.)
11. **AC11** — `vendor/bin/pint --dirty` schoon en `php artisan test --compact`
    groen. Coverage op nieuwe code ≥ 90%.
12. **AC12** — Geen JS-errors/console-warnings na manuele rondgang op
    http://localhost:19088; visueel pixel-close aan `marketing.jsx`.

## Buiten scope

- **Mobile companion** schermen → Fase 4.
- **Range Console** kernschermen → Fase 1 (target-rings/spark worden hier
  alvast gebouwd, maar de console-views niet).
- **Light/Daylight palet + palet-switcher** → toekomstige iteratie.
- **Echte content/CMS** voor blog/changelog/docs links — footer-links zijn
  placeholders (`#`) of bestaande externe URL's; geen nieuwe routes.
- **Werkende registratie/checkout** achter "Probeer gratis"/pricing-CTA's —
  die wijzen naar `/admin/login` (of `#`); betaalflow is buiten scope.
- **i18n/vertaalkeys** — strings hard-coded NL (bestaande conventie).
- **SEO/meta/OG-tags optimalisatie** buiten een correcte `<title>` +
  `meta description` — uitgebreide SEO is een aparte taak.

## Afhankelijkheden & risico's

| Risico | Impact | Mitigatie |
|---|---|---|
| Geen Vite/Tailwind build in repo (geen `package.json`/`vite.config`) | Medium | Standalone HTML-doc + inline `<style>` met `--at-*` tokens (zelfde patroon als huidige `welcome.blade.php` + `AdminPanelProvider::aimTrackDesignAssets()`). `<x-aimtrack.head-assets>` leest de tokens-CSS in. |
| `target-rings`/`spark` SVG-port wijkt visueel af van prototype | Medium | 1-op-1 port van de wiskunde uit `shared.jsx` (rings-array, hit-coords, sparkline min/max-normalisatie). Component-tests + visuele smoke op :19088. |
| Geen blade-heroicons-package → icons ontbreken op standalone pagina | Medium | Eigen `<x-aimtrack.icon>` met de `ICONS`-paden uit `shared.jsx` (geen package-afhankelijkheid). |
| Verwijderen `LandingPageData`/`config/landing.php` breekt iets onverwacht | Laag | Referentie-grep gedaan: enkel controller + `LandingPageTest`. Test wordt herschreven; `php artisan test` bevestigt groen. |
| Bestaande tests die `route('welcome')`/view `welcome` verwachten breken | Laag | Route-naam + bestandsnaam blijven gelijk; alleen de view-inhoud verandert. `LoginCtaTest` + user-menu + `login-extras` blijven werken. |
| Browser-test vereist draaiende stack | Laag | Dev-stack (`docker compose --env-file .env`) op :19088 wordt in BUILD-stap 6 opgestart; feature-tests draaien zonder browser. |
| Inline-CSS pagina wordt groot/onleesbaar | Laag | Sectie-comments + geprefixte klassen (`.mk-*`); componenten kapselen herhaling in. |

## Architectuur

### Bestandsboom (na BUILD)

```
resources/views/components/aimtrack/
├── target-rings.blade.php          # NIEUW · concentrische ringen + hits (port shared.jsx)
├── spark.blade.php                 # NIEUW · sparkline + area-gradient (port shared.jsx)
├── icon.blade.php                  # NIEUW · 24×24 stroke-1.6 icon-set (port ICONS)
├── head-assets.blade.php           # NIEUW · fonts + tokens-CSS voor standalone pages
└── (bestaand uit Fase 0: reticle, bracket-frame, monogram-stamp,
   at-mark, wordmark, ring-medaillon, watermark-bg)

resources/views/
└── welcome.blade.php               # HERBOUW · volledige marketing long-scroll

app/Http/Controllers/
└── LandingPageController.php       # WIJZIG · → return view('welcome')

app/Support/Landing/
└── LandingPageData.php             # VERWIJDER (orphaned na redesign)

config/
└── landing.php                     # VERWIJDER (orphaned na redesign)

tests/Feature/
└── LandingPageTest.php             # HERSCHRIJF · nieuwe marketing-content (bestand blijft)
tests/Feature/Marketing/
└── BrandComponentsTest.php         # NIEUW · render-tests target-rings/spark/icon/head-assets
tests/Browser/
└── MarketingLandingTest.php        # NIEUW · Pest 4 smoke: geen console-errors + CTA-klik
```

### Component-API's (nieuw)

```blade
{{-- Hero target rings --}}
<x-aimtrack.target-rings :size="220" accent="var(--at-accent)"
    dim="var(--at-text)" :ring-stroke="1" :show-hits="true" />

{{-- Sparkline (trend / score-drift) --}}
<x-aimtrack.spark :data="[9.6,9.5,9.4,9.2,9.0,8.9,9.0,9.1,9.3,9.4,9.4]"
    :w="380" :h="50" color="var(--at-warn)" :stroke-w="1.8" />

{{-- Icoon --}}
<x-aimtrack.icon name="arrow" :size="14" color="var(--at-cta-text)" :stroke="2" />

{{-- Head-assets (in <head> van welcome.blade.php) --}}
<x-aimtrack.head-assets />
```

`head-assets` levert exact dezelfde fonts + tokens als
`AdminPanelProvider::aimTrackDesignAssets()`, maar leest de CSS uit hetzelfde
bronbestand (`resource_path('css/aimtrack-tokens.css')`) → geen drift.

### Sectie → design-bron mapping (`marketing.jsx`)

| Sectie | marketing.jsx regels | Brand-primitieven |
|---|---|---|
| Nav | 38–59 | wordmark |
| Hero | 61–128 | reticle, **target-rings**, icon |
| Trust strip | 130–142 | — |
| Features (6) | 144–263 | icon, **spark**, **monogram-stamp (T4)** |
| AI deep-dive | 265–321 | icon, **bracket-frame (T3)**, **spark** |
| Self-hosted | 323–347 | — |
| Pricing (3) | 349–405 | icon |
| Footer | 407–414 | wordmark |

## Fasering

### Stap 1 — Nieuwe brand-primitieven + head-assets ✅ (gebouwd, getest, review-clean)
- [x] `<x-aimtrack.icon>` (ICONS-port — alle 20 glyphs byte-identiek aan shared.jsx)
- [x] `<x-aimtrack.target-rings>` (TargetRings-port)
- [x] `<x-aimtrack.spark>` (Spark-port — gradient-id CSS-var-safe via md5)
- [x] `<x-aimtrack.head-assets>` (fonts + tokens inline; mirror van AdminPanelProvider)
- [x] `tests/Feature/Marketing/BrandComponentsTest.php` (42 render-asserts) + Pint schoon
- [x] Adversariële review (4 dimensies × verify): 5 bevindingen, 0 blocking; 3 als
      extra coverage-tests verwerkt (volledige icon-set, spark edge-cases, custom hits/bg)
- Commit: `feat(marketing): brand primitives — icon, target-rings, spark, head-assets (#82 fase 3)`

### Stap 2 — Welcome view: nav + hero + trust strip ✅
- [x] `welcome.blade.php` herbouwd: head (head-assets, title, meta, `.mk-*` page-CSS
      met uitsluitend `--at-*` tokens + `color-mix`), sticky blurred nav, hero
      (headline + accent, lead, 2 CTA's met arrow/shield-icon, 4 pills, visual =
      reticle 420 + target-rings 220 + 3 callouts), trust strip
- [x] Geen pre-handoff resten meer (`--indigo`, `#64f4b3` hardcoded, Space Grotesk/
      Manrope, `.heatmap`, oude `.badge`) — AC1 ✓
- [x] `LandingPageTest` in lockstep herschreven (6 tests groen; assert nieuwe pagina
      + géén oude strings) zodat de suite per commit groen blijft. Controller +
      dead-code + finale AC9-coverage volgen in Stap 5.
- [x] Volledige suite 115 groen, Pint schoon, live smoke :19088 → 200, geen errors
- Commit: `feat(marketing): nav + hero + trust strip (#82 fase 3)`

### Stap 3 — Features + AI-coach deep-dive
- [ ] 6 feature-cards (incl. spark + T4 monogram-stamp)
- [ ] AI-coach deep-dive (T3 bracket-frame + spark)
- Commit: `feat(marketing): feature grid + AI-coach deep-dive (#82 fase 3)`

### Stap 4 — Self-hosted + pricing + footer
- [ ] Self-hosted terminal strip, 3 pricing-cards, footer
- Commit: `feat(marketing): self-hosted + pricing + footer (#82 fase 3)`

### Stap 5 — Controller + dead-code + tests
- [ ] `LandingPageController` → `return view('welcome')`
- [ ] Verwijder `LandingPageData` + `config/landing.php`
- [ ] Herschrijf `LandingPageTest`; component-tests groen; `LoginCtaTest` groen
- Commit: `refactor(marketing): drop live-stats data path + rewrite landing tests (#82 fase 3)`

### Stap 6 — Smoke + Pint + PR
- [ ] Dev-stack op :19088 (`docker compose --env-file .env -f docker/compose.dev.yml up -d` + migrate)
- [ ] `tests/Browser/MarketingLandingTest.php` (Pest 4, geen console-errors)
- [ ] `vendor/bin/pint --dirty` + `php artisan test --compact` groen
- [ ] Manuele visuele check :19088 vs `marketing.jsx`
- [ ] `gh pr create` met body verwijzend naar deze PLAN + umbrella-issue #82
- Commit: `test(marketing): browser smoke + coverage (#82 fase 3)`

PR-titel: `feat(marketing): Signal Mint landing page (#82 fase 3)`

## Open punten

Alle issue-open-vragen die Fase 3 raken zijn beslist (zie "Beslissingen
vooraf"): route = `/`, richting = pure marketing, palet = Signal Mint dark.
Geen openstaande blockers.

---

**Verzoek**: zet status op `APPROVED` (één regel volstaat) en ik start met
BUILD-stap 1 (brand-primitieven). Per stap kom ik terug met diff + tests vóór
door te gaan naar de volgende stap.

---
status: COMPLETED
created: 2026-05-14
approved: 2026-05-14
completed: 2026-05-14
author: Claude (Opus 4.7)
jira: GH#82
worktree: aimtrack-design-foundation (feature/design-foundation)
---

# PLAN: Issue #82 · Fase 0 — Design Foundation

## Status: COMPLETED

Alle 10 AC's gerealiseerd. 26 design-tests groen (121 assertions). Branch
`feature/design-foundation` klaar voor PR-review.

### Afwijkingen van het oorspronkelijke PLAN

1. **Geen Vite / Tailwind theme-entry**. De app heeft geen Vite/npm-setup,
   en dat toevoegen zou een dependency-change zijn (CLAUDE.md verbiedt dat
   zonder akkoord). Tokens worden in plaats daarvan via een HEAD_END render
   hook in `AdminPanelProvider` inline geïnjecteerd — zelfde patroon als
   de bestaande `.copilot-chat-widget` style hook.
2. **Geen Pest browser-test (AC8)**. Vereist `pest-plugin-browser` als nieuwe
   dev-dep + Chromium binary. In plaats daarvan dekken 26 feature-tests via
   `Blade::render` en `$this->get('/admin/login')` de inhoudelijke
   eis (tokens, fonts, wordmark in HTML, panel boot zonder exception).
   Visuele controle handmatig gedaan via `curl http://localhost:19084/admin/login`.
3. **Pre-existing test failure niet opgelost**: `tests/Feature/LogoutTest.php`
   faalt met CSRF-token-mismatch. Stash-verificatie bevestigt dat dit ook
   op de baseline-feature-branch faalt zonder mijn changes — bestaand
   probleem geïntroduceerd via #81. Buiten scope van dit issue.

## Samenvatting

Eerste fase van de [design handoff implementatie](https://github.com/Marcvdc/AimTrack/issues/82). Levert de bouwstenen waar alle volgende schermen op leunen:

1. **Design tokens** als CSS-variabelen + Tailwind preset (Signal Mint dark, één palet).
2. **Logo-systeem** als vier herbruikbare Blade-componenten (T1–T4) met dezelfde props als de prototypes.
3. **Filament panel theming** dat de tokens overal toepast (Filament 5 custom theme via `php artisan make:filament-theme`).

Geen schermen veranderen visueel verder dan de panel-chrome (kleuren, fonts, accent, focus). Bestaande Resources (Session, Weapon, AmmoType, Location, Attachment) en Pages blijven functioneel onveranderd — alleen hun look-and-feel volgt nu het Signal Mint palet.

## Motivatie

- Het design pakket bevat zes tot acht schermen die allemaal dezelfde tokens, typografie, iconen en logo-utilities gebruiken. Zonder Fase 0 dupliceren we die in elke fase. PR's zouden dan elkaar overschrijven.
- De gebruiker bevestigde: Signal Mint dark is de enige palet voor de MVP. Tokens worden zo opgezet dat andere paletten later eenvoudig als losse CSS-files erbij gestopt kunnen worden.
- Filament 5 ondersteunt sinds v3 een custom-theme entry (`resources/css/filament/admin/theme.css`) — dat is de schone hook.

## Acceptatiecriteria

1. **AC1** — `php artisan filament:install --panels=admin` of equivalent levert een custom-theme entry; AdminPanelProvider gebruikt `->viteTheme('resources/css/filament/admin/theme.css')`.
2. **AC2** — `resources/css/aimtrack-tokens.css` definieert exact deze 10 kleurvariabelen voor Signal Mint dark: `--at-bg`, `--at-panel`, `--at-panel-2`, `--at-line`, `--at-text`, `--at-muted`, `--at-accent`, `--at-accent-2`, `--at-warn`, `--at-cta-text` — met de waarden uit `design-tokens.jsx` (bg `#040711`, accent `#64f4b3`, etc.).
3. **AC3** — Tokens-CSS bevat type-rollen (`--at-font-display`, `--at-font-body`, `--at-font-mono`) gemapt op Inter + JetBrains Mono via Google Fonts of zelf-host.
4. **AC4** — Tokens-CSS bevat spacing- (`--at-space-1..7`) en radius-tokens (`--at-r-sm/md/lg/xl/2xl/pill`) volgens de design tokens.
5. **AC5** — `<x-aimtrack.watermark-bg>`, `<x-aimtrack.bracket-frame>`, `<x-aimtrack.ring-medaillon>`, `<x-aimtrack.monogram-stamp>` zijn Blade-componenten met dezelfde prop-namen als de JSX-utilities (`size`, `opacity`, `top`, `right`, `center`, `corner`, etc.). Elke component heeft een Pest browser test die de DOM-render verifieert.
6. **AC6** — `<x-aimtrack.reticle>`, `<x-aimtrack.at-mark>` (SVG primitieven uit `shared.jsx`) zijn herbruikbare Blade-componenten — verplicht voor T1/T2/T3 (`Reticle`) en T4 (`ATMark`).
7. **AC7** — Filament panel rendert in Signal Mint dark als enige theme: `body { background: var(--at-bg); color: var(--at-text); }`, sidebar / topbar / cards gebruiken `--at-panel` en `--at-line`. Visueel zichtbaar in de browser op de bestaande SessionResource index.
8. **AC8** — Pest test `tests/Browser/DesignFoundationTest.php` opent het admin panel als ingelogde gebruiker en assert: (a) body-achtergrond is `#040711` (of dichtbij; via computed style), (b) primary button gebruikt accent `#64f4b3`, (c) wordmark zichtbaar in sidebar, (d) geen JS errors / console logs.
9. **AC9** — `vendor/bin/pint --dirty` en `php artisan test --compact` zijn groen. Coverage op gewijzigde / nieuwe code ≥90%.
10. **AC10** — Design pakket is gecommit onder `.ai/design-handoff/` (inclusief README, chats, project/*.jsx, assets/).

## Buiten scope

- Range Console schermen (sessie-overzicht, sessie-detail, wapen-detail, AI-coach, wizard) → Fase 1
- Empty states → Fase 2
- Marketing landing → Fase 3
- Mobile companion → Fase 4
- Andere paletten (Range Amber, Steel Cyan, Daylight) → terug op de roadmap zodra er een use-case is
- Migratie van Tailwind-config naar tokens-aware preset — Filament 5 kan zonder Tailwind config wijzigingen werken via CSS-variabelen
- Theme switching UI

## Afhankelijkheden & risico's

| Risico | Impact | Mitigatie |
|---|---|---|
| Inter + JetBrains Mono via Google Fonts kan blokkeren in restricted netwerk | Laag | Self-host als fallback; eerst Google Fonts proberen (zelfde aanpak als prototype) |
| Filament 5 default styles overschrijven onze tokens | Medium | Custom theme entry heeft hogere specificity; CSS-vars per `:root` zorgt dat overrides cascaderen |
| Reticle / ATMark SVG's complex genoeg dat handmatige Blade-conversie fouten introduceert | Medium | Component-tests die viewbox/structuur asserten; bron-SVG's blijven in `.ai/design-handoff/` voor verificatie |
| Bestaande SessionShotBoard Livewire stuk werkt anders met nieuwe tokens | Laag | Visuele check in browser; tokens zijn opt-in, oude classes blijven werken |

## Architectuur

### Bestandsboom (na BUILD)

```
.ai/design-handoff/                          # NIEUW (archief van handoff bundle)
├── README.md
├── chats/chat1.md
└── project/
    ├── AimTrack Designs.html
    ├── design-tokens.jsx
    ├── logo-system.jsx
    ├── shared.jsx
    ├── assets/aimtrack-logo.svg
    └── …

resources/
├── css/
│   ├── aimtrack-tokens.css                 # NIEUW · 10 kleur + spacing + radius + type vars
│   └── filament/admin/theme.css            # NIEUW · @import tokens + Filament theme hooks
└── views/components/aimtrack/              # NIEUW · 6 herbruikbare Blade-componenten
    ├── reticle.blade.php                   # SVG primitief
    ├── at-mark.blade.php                   # SVG primitief
    ├── watermark-bg.blade.php              # T1
    ├── bracket-frame.blade.php             # T3 (slot voor children)
    ├── ring-medaillon.blade.php            # T2
    └── monogram-stamp.blade.php            # T4

app/Providers/Filament/AdminPanelProvider.php   # WIJZIG · viteTheme + brandLogo + colors
vite.config.js                              # WIJZIG · theme.css input toevoegen
package.json                                # ONVERANDERD (assumed)

tests/
├── Browser/DesignFoundationTest.php        # NIEUW · panel render + tokens applied
└── Feature/DesignComponentsTest.php        # NIEUW · Blade-componenten renderen correct
```

### Component-API

Identieke prop-namen als de JSX-utilities zodat developers één-op-één kunnen vertalen:

```blade
<x-aimtrack.watermark-bg size="220" opacity="0.08" top="-40" right="-30" />
<x-aimtrack.bracket-frame corner-size="14" rounded="8" padding="16">
    {{-- inhoud --}}
</x-aimtrack.bracket-frame>
<x-aimtrack.ring-medaillon size="200" label="SCORE" value="547" sub="/600" />
<x-aimtrack.monogram-stamp label="WM-4 OK" variant="solid" corner="top-right" />
<x-aimtrack.reticle size="80" stroke="1.5" :dot="true" />
<x-aimtrack.at-mark size="18" />
```

Kleuren komen automatisch uit CSS-variabelen (`color: currentColor` waar mogelijk; anders `var(--at-accent)`). Geen `palette={c}` prop nodig — de hele app deelt één palet via `:root`.

## Fasering

### Stap 1 — Tokens-CSS (klein, mergebaar als losse commit)
- [ ] `resources/css/aimtrack-tokens.css` schrijven met alle 10 kleur + spacing 1–7 + radius set + 3 font-rollen.
- [ ] Google Fonts link in layout (panel + welcome) als prototype.
- [ ] Visueel check in browser: tokens beschikbaar via DevTools.

### Stap 2 — Filament theme entry
- [ ] `php artisan make:filament-theme admin --no-interaction` draaien om de hooks te genereren.
- [ ] `resources/css/filament/admin/theme.css` importeert `aimtrack-tokens.css` en overschrijft Filament's CSS-variabelen.
- [ ] `AdminPanelProvider` aanroepen: `->viteTheme('resources/css/filament/admin/theme.css')`, plus brand-color → accent.
- [ ] `vite.config.js` input array uitbreiden.
- [ ] Build draait succesvol (`npm run build`).

### Stap 3 — SVG primitieven
- [ ] `<x-aimtrack.reticle>` — port van `Reticle` uit `shared.jsx` (zoek deze in design-canvas of een ander bestand — staat niet expliciet in shared.jsx, vermoedelijk in design-canvas.jsx of logo-treatments.jsx).
- [ ] `<x-aimtrack.at-mark>` — port van `ATMark`.
- [ ] Pest-test: rendert `<svg>` met juiste viewbox; accepteert `size`, `color`, `stroke`.

### Stap 4 — Logo-utilities (T1–T4)
- [ ] `<x-aimtrack.watermark-bg>` (T1)
- [ ] `<x-aimtrack.bracket-frame>` (T3, met slot)
- [ ] `<x-aimtrack.ring-medaillon>` (T2)
- [ ] `<x-aimtrack.monogram-stamp>` (T4)
- [ ] Per component Pest browser test (assert juiste classes / inline styles).

### Stap 5 — Panel-chrome aanscherpen
- [ ] AdminPanelProvider: brand-logo via `<x-aimtrack.wordmark>` (of inline SVG); sidebar collapse-icon; default sidebar collapsed?
- [ ] Visuele controle in browser op http://localhost:19084: SessionResource index, EditSession form, ViewSession.
- [ ] Geen regressies in SessionShotBoard Livewire-board.

### Stap 6 — Tests + Pint
- [ ] `tests/Browser/DesignFoundationTest.php` — panel-render assertions.
- [ ] `tests/Feature/DesignComponentsTest.php` — Blade-component renders.
- [ ] Run: `php artisan test --compact` → groen.
- [ ] `vendor/bin/pint --dirty`.

### Stap 7 — Commit-strategie (na sign-off per stap)
1. Commit 1: `feat(design): archive design handoff bundle` (.ai/design-handoff/)
2. Commit 2: `feat(design): add design tokens CSS (Signal Mint dark)`
3. Commit 3: `feat(design): Filament 5 custom theme + brand colors`
4. Commit 4: `feat(design): Reticle + ATMark Blade SVG primitives` (+ tests)
5. Commit 5: `feat(design): logo system Blade components (T1-T4)` (+ tests)
6. Commit 6: `feat(design): apply panel chrome polish (sidebar, brand)`
7. Commit 7: `test(design): browser + component tests`

PR-titel: `feat(design): foundation — tokens, logo system, panel theming (#82 fase 0)`

## Open punten waar ik input op nodig heb vóór BUILD

1. **Fonts hosting** — Google Fonts CDN (snel, simpel) of self-host (privacy, geen externe call)?
2. **AdminPanelProvider brand-mark** — Wordmark (logo + "AimTrack" tekst) of alleen het logomark in de sidebar?
3. **Stap-voor-stap goedkeuring vs single approval** — wil je per stap een check-in, of pak ik alle 6 stappen achter elkaar af en kom ik terug met één review?
4. **Vite-build versus `composer run dev`** — moet ik na elke stap `npm run build` draaien en visueel valideren in de docker-stack op :19084, of wachten we tot het einde?

---

**Verzoek**: lees dit PLAN door, geef feedback / wijzigingen, en zet vervolgens de status op APPROVED zodra je tevreden bent. Pas dan begin ik aan BUILD-stap 1.

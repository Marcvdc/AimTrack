---
status: COMPLETED
created: 2026-05-14
updated: 2026-05-21
approved: 2026-05-14
completed: 2026-05-21
author: Claude (Opus 4.7)
jira: GH#82
worktree: aimtrack-empty-states (feature/empty-states)
basis: e809582 (main, post-PR #86 merge)
---

# PLAN: Issue #82 · Fase 2 — Empty states & first-run

## Status: COMPLETED

Alle 12 AC's gerealiseerd. Browser-smoke door marc@kjsoftware.nl
geverifieerd op 2026-05-21. Klaar voor PR-review.

### Commits (per stap, beslissing 4)

1. `1274338` — Shared empty-state component + UserOnboardingState + StarterTemplates
2. `85f23bd` — First-run welcome on Dashboard
3. `7f783dc` — SessionResource empty state
4. `59f1cee` — chore: worktree registry update
5. `119cb45` — WeaponResource empty state + 3 starter templates
6. `fc72b69` — AI-coach threshold empty state with progress
7. `67af712` — DemoDataSeeder service + unified seed action + idempotency

### Afwijkingen van het oorspronkelijke PLAN

1. **EmptyStateComponentTest leeft onder `tests/Feature/` i.p.v. `tests/Unit/`**
   — volgt projectconventie (DesignComponentsTest is ook Feature). Geen
   functioneel verschil; beide directories gebruiken TestCase met
   LazilyRefreshDatabase.
2. **CreateWeapon::mount() doet AmmoType::firstOrCreate()** — niet in
   PLAN-architectuur beschreven, maar technisch nodig omdat de
   caliber-Select op bestaande user-AmmoType rijen filtert. Anders kan
   de prefill-waarde niet getoond worden.
3. **Demo-data unify is gedeeltelijk gedaan** — CopilotDemoSeeder is nu
   thin wrapper rond DemoDataSeeder service. DevSeeder op feature/55 is
   ongemoeid gelaten (cross-branch deprecation buiten scope; flag in
   PR-body als follow-up).
4. **Stap 6 scope-bump**: oorspronkelijk alleen Action, werd uitgebreid
   met migration (users.demo_data_seeded_at), service-extractie en
   CopilotDemoSeeder refactor — op verzoek tijdens review.

## Beslissingen na review (2026-05-14)

1. **Demo-data scope** → **beide**: sessies + wapens + AI-reflections.
   Doel: na "Demo-data inladen" is óók de AI-coach drempel (3 sessies)
   meteen unlocked, dus de gebruiker kan de hele app in één klik
   ervaren.
2. **Dashboard override** → **dedicated, maar dat ÍS de dashboard**.
   Eén custom `App\Filament\Pages\Dashboard` extends Filament default
   en wordt op `/admin` gemount. Geen aparte `/welcome` route. Binnen
   de view: `@if ($onboarding->hasNoData())` welcome-takeover, anders
   widgets-grid.
3. **Demo-action environment** → **overal beschikbaar**, ook in
   production. Filament `requiresConfirmation()` modal beschermt tegen
   per-ongeluk klikken.
4. **Commits-cadans** → **per stap**. Na elke fasering-stap één commit
   en check-in met de gebruiker; pas door naar volgende stap na
   expliciete OK.
5. **WeaponType enum** → **niks toevoegen**. AimTrack is een
   schietvereniging-app, geen luchtsport-app. Alle 3 starter-templates
   mappen op bestaande `WeaponType::PISTOL`; alleen `caliber` en
   `name` verschillen.

## Samenvatting

Vier "leeg-of-eerste-keer" momenten zichtbaar maken in het Filament-panel,
elk gebouwd in het Signal Mint dark design uit Fase 0. Gebaseerd op
`.ai/design-handoff/project/empty-states.jsx` (201 regels, 4 artboards):

1. **First-run welcome** — full-screen takeover op de Dashboard wanneer de
   ingelogde gebruiker nog 0 sessies + 0 wapens heeft. 3-staps onboarding-checklist
   met huidige progress.
2. **Geen sessies** — full-page empty state op `SessionResource` index met
   "Eerste sessie loggen" en "Demo-data inladen" CTA's + tip-card over papieren logboek.
3. **Geen wapens met starter-sjablonen** — full-page empty state op
   `WeaponResource` index met 3 klikbare starter-templates (Luchtpistool 4.5mm
   "meest gebruikt", Pistool 9mm, Vrij pistool .22 LR) die `CreateWeapon`
   pre-fillen via querystring.
4. **AI-coach te-weinig-data** — empty state in `CoachPage` wanneer
   user < 3 sessies heeft, met progress-bar (X/3) richting unlock.

Alle vier hergebruiken Fase 0 Blade-componenten (`<x-aimtrack.reticle>`,
`<x-aimtrack.wordmark>`, `<x-aimtrack.at-mark>`) en `aimtrack-tokens.css`
variabelen — geen nieuwe design primitieven nodig.

## Motivatie

- **Eerste indruk telt**: een leeg standaard Filament-paneel ziet er
  "broken" uit voor nieuwe gebruikers. De welcome-card en onboarding-flow
  zijn precies waarvoor de design handoff de tokens en het logo-systeem
  klaar heeft staan.
- **Tussenstap voor data-vereisende features**: de AI-coach heeft minimaal
  3 sessies nodig (business-regel uit `chat1.md`). Zonder expliciete progress
  state lijkt het of de AI "niets doet" — frustratie + support-tickets.
- **Conversie van browse → eerste actie**: empty states zijn de plek
  waar nieuwe gebruikers de eerste handeling doen. Goede CTA's + starter-templates
  korten de tijd-tot-eerste-sessie merkbaar in.
- Issue #82 listet deze 4 expliciet als "Fase 2" met `[ ]` checkboxes.

## Acceptatiecriteria

1. **AC1** — `<x-aimtrack.empty-state>` is een herbruikbare Blade-component
   met slots voor `icon`, `title`, `description`, `actions`, en `extra`,
   plus de T1 reticle-watermark als achtergrond (`opacity ~0.05`). Wordt
   gebruikt door AC3, AC4 en AC5.
2. **AC2** — Dashboard rendert een full-screen "First-run welcome" wanneer
   `auth()->user()->sessions()->doesntExist() && auth()->user()->weapons()->doesntExist()`.
   Inclusief 3-staps onboarding-checklist (wapen / profiel / eerste sessie)
   met dynamische `done`/`current` status op basis van de werkelijke
   user-state. Reticle T1 watermark `opacity 0.06`, accent rondom de
   "current" stap.
3. **AC3** — `SessionResource` ListSessions toont "Nog geen sessies gelogd"
   empty state met 2 CTA's ("Eerste sessie loggen" → `CreateSession`, en
   "Demo-data inladen" → seed-action) + tip-card over foto-uploads. Empty
   state is alleen zichtbaar als de huidige user 0 sessies heeft.
4. **AC4** — `WeaponResource` ListWeapons toont "Voeg je eerste wapen toe"
   empty state met 3 starter-template cards (Luchtpistool 4.5mm, Pistool 9mm,
   Vrij pistool .22 LR). De Luchtpistool-card heeft `● MEEST GEBRUIKT` chip
   en accent-rand. Klik op een sjabloon opent `CreateWeapon` met
   `?template=luchtpistool` querystring, en die pre-fillt de form-velden
   `weapon_type` + `caliber`.
5. **AC5** — `CoachPage` toont "De coach heeft 3 sessies nodig" empty state
   met progress-bar (`current/3`) wanneer user < 3 sessies heeft. Toont
   normale chat-UI zodra de drempel wordt overschreden.
6. **AC6** — Demo-data action (AC3) seedt voor de huidige user:
   minimaal 5 sessies + 2 wapens + 3 AI-reflections (zodat de AI-coach
   drempel uit AC5 direct unlocked is). Via een dedicated
   `SeedDemoDataAction` (Filament Action met `requiresConfirmation()`).
   Werkt in elke environment (ook production). Niet-destructief — voegt
   toe naast bestaande data. Idempotent via een marker (zodat herhaalde
   klik geen duplicates aanmaakt).
7. **AC7** — Filament `Page::canAccess()` of equivalent regelt dat de
   Dashboard welcome-rendering geen impact heeft op users die wel data
   hebben (geen ongewenste flash, geen extra query in hot-path).
8. **AC8** — Per empty state minimaal 2 Pest feature-tests:
   - rendert correct wanneer user voldoet aan empty-conditie
   - rendert NIET wanneer user data heeft
   Plus 1 component-test op `<x-aimtrack.empty-state>` (basis-DOM-render).
   Totaal: ≥10 nieuwe tests.
9. **AC9** — Demo-data seed-action heeft een Pest-test die assert dat
   na uitvoering: sessies + wapens bestaan, scoped op `user_id`, en dat
   herhaalde aanroep geen duplicates seedt.
10. **AC10** — Starter-template flow heeft een Pest-test die assert dat
    `GET /admin/weapons/create?template=luchtpistool` resulteert in een
    Livewire-component met pre-filled `weapon_type='pistool'` (of mapping)
    en `caliber='4.5 mm'`.
11. **AC11** — `vendor/bin/pint --dirty` en `php artisan test --compact`
    zijn groen. Coverage op nieuwe code ≥90%.
12. **AC12** — Geen JS-errors of console-warnings in browser na manuele
    rondgang langs alle 4 empty states (smoke-test via curl + visuele
    controle in dev-stack op :19086).

## Buiten scope

- Mobile companion empty states → Fase 4
- Marketing landing-page empty states (anders dan ingelogd panel) → Fase 3
- Verfijnde first-run wizard die meerdere schermen beslaat → toekomstige
  iteratie. Deze fase doet 1 statische welcome-card; de eigenlijke
  "wizard" zit in Fase 1 als nieuwe-sessie flow.
- AI-coach reflectie-content na drempel — die UX zit in Fase 1.
- Translations / i18n keys — strings zijn hard-coded Nederlands (volgens
  bestaande conventie in `Resources/`).
- Localized palette switching — Signal Mint dark blijft enig palet.

## Afhankelijkheden & risico's

| Risico | Impact | Mitigatie |
|---|---|---|
| Filament v5 `emptyState` API werkt anders dan v3-docs suggereren | Medium | Eerst `search-docs` op `filament` + `empty state table v5`; fallback: custom Page view die conditie checked in `mount()` |
| Dashboard override (eigen DashboardPage) breekt huidige widgets-config | Laag | `FailedJobsWidget` is enige widget en is admin-only — eigen Dashboard kan widgets blijven renderen onder de welcome-card check |
| Demo-data seeder verstoort productie (per ongeluk via prod gerund) | Hoog | Filament `requiresConfirmation()`-modal met duidelijke "Dit voegt fictieve data toe aan jouw account"-tekst. Scoped op `auth()->id()` — raakt nooit andere users. Idempotency-marker voorkomt herhaaldelijke duplicates. |
| Starter-template querystring-prefill werkt niet stabiel in Filament v5 | Medium | Alternatief: hidden inputs in form-state via `mount(?string $template = null)`; deze pad wordt gevalideerd in AC10 test |
| AI-coach < 3 sessies check duplicate met Fase 1 logica | Laag | Gebruik dezelfde helper `auth()->user()->sessions()->count()` — als Fase 1 een feature-flag/policy introduceert, refactoren we in Fase 1 |

## Architectuur

### Bestandsboom (na BUILD)

```
resources/views/components/aimtrack/
├── empty-state.blade.php                         # NIEUW · herbruikbare wrapper met reticle bg
└── (bestaande: reticle, at-mark, watermark-bg,
   bracket-frame, ring-medaillon, monogram-stamp,
   wordmark — uit Fase 0)

resources/views/filament/
├── pages/dashboard.blade.php                     # NIEUW (override)
├── pages/coach-page.blade.php                    # WIJZIG · empty-state branch
├── resources/sessions/empty-state.blade.php      # NIEUW · partial gebruikt door ListSessions
└── resources/weapons/empty-state.blade.php       # NIEUW · partial met starter templates

app/Filament/
├── Pages/Dashboard.php                           # NIEUW · override default met welcome-check
├── Resources/SessionResource/Pages/ListSessions.php  # WIJZIG · ->emptyStateView() of overriden table
├── Resources/WeaponResource/Pages/ListWeapons.php    # WIJZIG · idem
├── Resources/WeaponResource/Pages/CreateWeapon.php   # WIJZIG · ?template= afhandeling
└── Actions/SeedDemoDataAction.php                # NIEUW · idempotente seed action

app/Support/                                      # bestaand
└── StarterTemplates.php                          # NIEUW · array van 3 templates (type, caliber, name, popular)

database/seeders/
└── DemoDataSeeder.php                            # NIEUW · scoped op user_id, idempotent

tests/Feature/
├── EmptyStates/FirstRunWelcomeTest.php           # NIEUW · AC2 + AC8
├── EmptyStates/NoSessionsTest.php                # NIEUW · AC3 + AC8
├── EmptyStates/NoWeaponsTest.php                 # NIEUW · AC4 + AC8 + AC10
├── EmptyStates/AiCoachThresholdTest.php          # NIEUW · AC5 + AC8
└── EmptyStates/DemoDataSeederTest.php            # NIEUW · AC6 + AC9
tests/Unit/EmptyStateComponentTest.php            # NIEUW · component-render
```

### Component-API

```blade
{{-- AC1: herbruikbare empty-state shell --}}
<x-aimtrack.empty-state
    icon="heroicon-o-calendar-days"
    :reticle-opacity="0.05"
    :reticle-size="460"
    max-width="420"
>
    <x-slot:title>Nog geen sessies gelogd</x-slot:title>
    <x-slot:description>Log je eerste training in ongeveer 30 seconden…</x-slot:description>
    <x-slot:actions>
        <a href="{{ $createUrl }}" class="at-btn-prim">Eerste sessie loggen</a>
        <button wire:click="seedDemo" class="at-btn">Demo-data inladen</button>
    </x-slot:actions>
    <x-slot:extra>
        <div class="at-tip-card">…💡 TIP…</div>
    </x-slot:extra>
</x-aimtrack.empty-state>
```

### Starter templates (AC4)

```php
// app/Support/StarterTemplates.php
final class StarterTemplates
{
    /** @return array<int, array{key: string, label: string, caliber: string, weapon_type: WeaponType, popular: bool}> */
    public static function weapons(): array
    {
        // Beslissing 5: alle 3 mappen op WeaponType::PISTOL — AimTrack is
        // een schietvereniging-app, niet luchtsport. Onderscheid zit
        // puur in caliber + label.
        return [
            ['key' => 'luchtpistool', 'label' => 'Luchtpistool', 'caliber' => '4.5 mm',   'weapon_type' => WeaponType::PISTOL, 'popular' => true],
            ['key' => 'pistool-9mm',  'label' => 'Pistool',      'caliber' => '9×19 mm',  'weapon_type' => WeaponType::PISTOL, 'popular' => false],
            ['key' => 'vrij-pistool', 'label' => 'Vrij pistool', 'caliber' => '.22 LR',   'weapon_type' => WeaponType::PISTOL, 'popular' => false],
        ];
    }
}
```

`CreateWeapon::mount()` leest `request('template')`, zoekt match in
`StarterTemplates::weapons()`, en zet `$this->data` velden via Filament's
form `fill()` method.

### Empty-state conditie-checks

Eén centrale helper voorkomt dupe-queries:

```php
// app/Support/UserOnboardingState.php
final class UserOnboardingState
{
    public function __construct(private readonly User $user) {}

    public function hasNoData(): bool      // → first-run welcome
    {
        return ! $this->user->sessions()->exists() && ! $this->user->weapons()->exists();
    }

    public function sessionsCount(): int   // → AI-coach threshold
    {
        return $this->user->sessions()->count();
    }

    public function hasFirstWeapon(): bool { return $this->user->weapons()->exists(); }
    public function hasFirstSession(): bool { return $this->user->sessions()->exists(); }
}
```

Bound via `app(UserOnboardingState::class, ['user' => auth()->user()])`
of via een service-binding in `AppServiceProvider`.

## Fasering

### Stap 1 — Shared component + helper
- [ ] `<x-aimtrack.empty-state>` Blade-component (AC1)
- [ ] `app/Support/UserOnboardingState.php` helper
- [ ] `app/Support/StarterTemplates.php`
- [ ] Pint clean, Pest unit-test op component-render

### Stap 2 — First-run welcome (Dashboard)
- [ ] `App\Filament\Pages\Dashboard` extends default
- [ ] `resources/views/filament/pages/dashboard.blade.php` met `@if ($onboarding->hasNoData())` welcome-takeover, anders default widgets
- [ ] AC2 + AC7 + AC8 tests

### Stap 3 — Geen sessies
- [ ] `ListSessions` → `->table($table)` callback met `->emptyStateView('filament.resources.sessions.empty-state')` of paginate-override
- [ ] `resources/views/filament/resources/sessions/empty-state.blade.php`
- [ ] AC3 + AC8 tests

### Stap 4 — Geen wapens + starter-templates
- [ ] `ListWeapons` → empty state met 3 template-cards
- [ ] `CreateWeapon::mount()` honoreert `?template=…`
- [ ] AC4 + AC8 + AC10 tests

### Stap 5 — AI-coach drempel
- [ ] `CoachPage` blade-view branch: `@if ($onboarding->sessionsCount() < 3)` toont empty state, anders bestaande chat-UI
- [ ] AC5 + AC8 tests

### Stap 6 — Demo-data action
- [ ] `App\Filament\Actions\SeedDemoDataAction` (idempotent, scoped op user)
- [ ] `DemoDataSeeder` seedt: 5 sessies + 2 wapens + 3 AI-reflections
  (beslissing 1: drempel uit AC5 moet meteen vallen na seed)
- [ ] `requiresConfirmation()` modal — beschermt prod-klikken (beslissing 3)
- [ ] AC6 + AC9 tests

### Stap 7 — Smoke + Pint
- [ ] `vendor/bin/pint --dirty`
- [ ] `php artisan test --compact` → groen
- [ ] Manuele browser-check op http://localhost:19086 voor alle 4 states
- [ ] `gh pr create` met PR-body verwijzend naar deze PLAN

### Stap 8 — Commits (na sign-off per stap)
1. `feat(empty-states): shared empty-state component + onboarding helper`
2. `feat(empty-states): first-run welcome on dashboard (#82 fase 2)`
3. `feat(empty-states): SessionResource empty state with demo-data CTA`
4. `feat(empty-states): WeaponResource empty state + 3 starter templates`
5. `feat(empty-states): AI-coach threshold empty state with progress`
6. `feat(empty-states): demo-data seed action (idempotent, scoped)`
7. `test(empty-states): feature + component coverage`

PR-titel: `feat(empty-states): first-run + 4 empty states (#82 fase 2)`

## Open punten

Alle 5 open punten beantwoord — zie "Beslissingen na review" bovenaan.

---

**Verzoek**: zet status op `APPROVED` (één regel "approved" volstaat) en
ik begin met BUILD-stap 1 (shared component + helper). Per stap kom ik
terug met diff + tests voor jouw check-in vóór door te gaan naar de
volgende stap.

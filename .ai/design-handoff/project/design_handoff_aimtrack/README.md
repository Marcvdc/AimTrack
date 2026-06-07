# Handoff: AimTrack — App, Marketing & Brand System

## Overview

AimTrack is an AI-powered **digital shooting log for recreational sport shooters**. The app lets users:

- Log training sessions (discipline, weapon, range, shots, scores)
- Get **per-session AI reflection** highlighting strong points, concentration dips, and concrete training advice
- Track progress per weapon (avg score, group, shot count, calibration)
- Export **WM-4 compliant** records for their shooting association
- Self-host or use the NL-hosted cloud — privacy-first, open-source under MIT

The product is **Dutch (NL)** and the primary frame is a **Filament 3.x admin panel** (PHP/Laravel) — though these designs are framework-agnostic. The existing Filament default theme is the starting point; this handoff replaces the visual skin with a tactical, brand-driven dark-mode UI.

---

## About the design files

The files in this bundle are **design references created in HTML** — interactive prototypes showing the intended look, layout, and behavior. They are NOT production code to copy verbatim.

Your task is to **recreate these designs in the AimTrack codebase's existing environment** — Filament 3.x with Tailwind CSS / Livewire / Alpine, plus a Laravel backend. Use the codebase's established patterns:

- For Filament panels: extend Filament's resource pages, custom widgets, and theme tokens
- For marketing: a standard Blade view or static page
- For mobile: native (iOS/Android) or a PWA — the bundle includes mobile mocks but does not prescribe the implementation tech

If a screen has no obvious Filament equivalent (the mobile companion, the marketing landing page), pick the most pragmatic framework — but keep the **design tokens identical** so everything visually feels like one product.

---

## Fidelity

**High-fidelity.** All screens use final colors, typography, spacing, and component behavior. Recreate them pixel-perfectly using the design tokens in the "Design Tokens" section below.

The HTML prototypes scale via JS to fit any viewport; the design canvas in `AimTrack Designs.html` is the reference. Use the **Tweaks panel** (bottom-right) to verify how each screen behaves across palettes and font presets — this is the same flexibility you must support in code.

---

## Brand foundation — the logo system

The AimTrack logomark (`assets/aimtrack-logo.svg`) consists of three elements:

1. A **circle** (ring) — the iris of an aperture / target ring
2. **N/S/E/W crosshair ticks** outside the ring
3. An **AT monogram** (interlocked letterforms) inside

These three primitives recur across the product as **four distinct treatments** with strict roles. This is the visual rode draad — do not invent new uses, do not stack two on the same surface.

| ID | Name | Role | Where to use | Where NOT |
|---|---|---|---|---|
| **T1** | **Reticle watermark** | Ambient atmosphere | Page-header backgrounds, KPI block backgrounds, hero sections, empty states | Small cards, table rows |
| **T2** | **Ring-as-frame** | Showpiece (max 1× per screen) | Score medallion, profile avatar ring, marketing hero | Twice on same screen |
| **T3** | **Crosshair brackets** | Emphasis on AI / live moments | AI-reflection cards, coach output, live-session indicators | Decoration on ordinary cards |
| **T4** | **Monogram stamp** | Trust / verification | WM-4 export badges, "verified" session, journal section dividers | Stacked with T3 on the same card |

**Stacking rules:**
- Max 1× T1 per screen + max 1× T2 per screen
- T3 and T4 may appear multiple times, but not together on the same card
- All four render in the accent color by default; muted gray is acceptable for neutral contexts (print, footer dividers)
- The warn (danger) color is forbidden — a red T3 would mislead users

The reference card at the top of the design canvas (`LogoSystemReference` in `logo-system.jsx`) shows all four in context with examples. Read it first.

---

## Design tokens

These values are the single source of truth. Implement them as CSS custom properties or Tailwind theme extensions.

### Colors — Signal Mint palette (default / "dark")

```css
--bg:        #040711;   /* page background gradient start */
--bg-end:    #0c1428;   /* page background gradient end */
--panel:     #0c1428;   /* card / module surface */
--panel-2:   #131c34;   /* subtle hover / inset */
--line:      #1c2543;   /* 1px separators & borders */
--text:      #e3ecff;   /* primary text */
--muted:     #94a3c5;   /* secondary text */
--accent:    #64f4b3;   /* CTA, data highlight, AI mint */
--accent-2:  #7a8cff;   /* indigo links / 2nd data channel */
--warn:      #f87171;   /* error / dip-marker */
--cta-text:  #051810;   /* text on --accent buttons */
```

### Alternate palettes

The user can switch palettes via Tweaks. Implement all four; default is Signal Mint.

```css
/* Range Amber */
--bg: #0a0907;  --panel: #16140f;  --panel-2: #201d16;
--line: #2c2820;  --text: #f0e9d8;  --muted: #a39880;
--accent: #ffb648;  --accent-2: #7a8cff;  --warn: #ef6b4f;
--cta-text: #1a1304;

/* Steel Cyan */
--bg: #06090c;  --panel: #0d141a;  --panel-2: #141d26;
--line: #1f2a36;  --text: #e6eef5;  --muted: #8aa0b3;
--accent: #5ac8d8;  --accent-2: #a5b4fc;  --warn: #f87171;
--cta-text: #031218;

/* Daylight (light mode) */
--bg: #f6f5f1;  --panel: #ffffff;  --panel-2: #f0eee9;
--line: #e2dfd6;  --text: #1a1f2e;  --muted: #6b7280;
--accent: #0f9968;  --accent-2: #4f46e5;  --warn: #dc2626;
--cta-text: #ffffff;
```

### Typography

Three font presets the user can toggle. **Default: Inter + JetBrains Mono.**

| Preset | Display + body | Mono (scores, data) |
|---|---|---|
| Default | Inter (400/500/600/700) | JetBrains Mono (400/500/600/700) |
| Editorial | Space Grotesk (400/500/600/700) | IBM Plex Mono (400/500/600) |
| Serif-led | Instrument Serif (display only) + DM Sans (body) | JetBrains Mono |

Type scale:

| Token | Size | Line-height | Weight | Use |
|---|---|---|---|---|
| `display/xl` | 64px | 1.02 | 600 | Marketing hero |
| `display/lg` | 44px | 1.05 | 600 | Section titles |
| `display/md` | 28px | 1.1 | 600 | Page titles |
| `heading` | 22px | 1.2 | 600 | Card titles |
| `subheading` | 17px | 1.3 | 600 | Sub-titles |
| `body` | 13px | 1.5 | 500 | Body / default |
| `small` | 12px | 1.45 | 500 | Tertiary text |
| `micro` | 11px | 1.4 | 500 | Captions / meta |
| `data/xl` (mono) | 44px | 1.0 | 600 | Hero scores |
| `data/lg` (mono) | 26px | 1.0 | 600 | KPI values |
| `data/md` (mono) | 18px | 1.0 | 600 | Inline data |
| `label` (mono) | 10px | 1.0 | 500 | UPPERCASE · 0.18em letter-spacing |

All mono numbers use `font-variant-numeric: tabular-nums` and slight negative letter-spacing (`-0.02em`) on large sizes.

### Spacing

4px-based scale:

```
--space-1: 4px    --space-5: 24px
--space-2: 8px    --space-6: 32px
--space-3: 12px   --space-7: 48px
--space-4: 16px
```

### Border radius

```
--r-sm:   4px    /* badge / tick */
--r-md:   6px    /* button / nav-item */
--r-lg:   8px    /* card / panel */
--r-xl:   12px   /* modal / hero */
--r-2xl:  18px   /* mobile card */
--r-pill: 999px  /* chip / tag */
```

### Borders & shadows

- All cards use `1px solid var(--line)` — no shadows for elevation
- Hover: lift to `var(--panel-2)` background
- Active/selected nav items: `2px solid var(--accent)` on the left edge

---

## Screens / views

The HTML prototypes are organized as a **design canvas** (open `AimTrack Designs.html` and pan/zoom, or open any artboard fullscreen via the expand icon on its top-right). The sections, in order:

### 0 · Logo system reference
**File**: `logo-system.jsx` → `LogoSystemReference`
**Size**: 1440×900
**Purpose**: Read this first. Lays out all four logo treatments with examples + stacking rules. Not a screen in the app — a reference page.

### 1 · Logo treatments (original comparison)
**File**: `logo-treatments.jsx`
**Size**: 360×280 each (4 cards)
**Purpose**: The four original proposals (T1–T4) that led to the system. Kept for reference only — implement the system in section 0, not these cards.

### 2 · Range Console — A · the main app (5 screens)

The chosen direction. All Range Console screens use a shared shell:

- **Left sidebar** (220px, `var(--panel)` bg) — Wordmark + nav with grouped sections ("log", "inzicht", "beheer"), each item is icon + label + optional badge. Active item: `var(--panel-2)` bg + 2px accent border on left. Footer: avatar (32×32, `var(--panel-2)` bg) + name + license code.
- **Topbar** (56px, `var(--bg)`) — breadcrumb (mono, 11px, 0.08em letter-spacing) + flex spacer + search (380px, `var(--panel)`) + action buttons
- **Body** — padding 24px, content varies per screen

Shared component file: `range-console.jsx` defines styles; `a-styles.jsx` exports the reusable `aStyles(c, fonts)` function; `a-screens.jsx` defines the three detail screens; `new-session-wizard.jsx` is the modal flow.

#### 2.1 · Sessies (overview) — `RangeConsole`
- **Page header**: H1 "Sessies" + sub. Has T1 reticle watermark (180px, opacity 0.08, top -30 right -20).
- **Stats row** (4 cards, 12px gap): Sessies/mnd · Schoten · Beste serie · AI-reflecties. Each card: `var(--panel)` bg, 1px `var(--line)` border, 8px radius, padding 16. Label (mono 10px 0.18em), big mono value (26px 600), sub with trend arrow.
- **Sessions table**: 247 records, paginated. Columns: Datum (mono small) · Discipline · Wapen (mono) · Schoten · Score (accent mono bold) · Status badge · More-menu. Row height ~52px, 1px border-bottom per row.
- **Weapons row**: 3-column grid of weapon usage cards.
- **Right column** (320px): Last session card (target rings + KPIs) + AI-reflection BracketFrame (T3) + 30-day trend.

#### 2.2 · Sessie-detail — `SessionDetail`
- **Header card**: T1 watermark + T4 "WM-4 OK" stamp at top-right + session metadata + big eindscore (44px mono accent).
- **Stats row** (5 KPIs): Beste schot · Tienen · Negens · Groep · Cadans.
- **Series card**: 6× 10-shot series with progress bars (each ≥95 colored accent, else muted).
- **Shot strip**: 60 bars (each 4–8px wide), green for 10+, warn-colored for the dip region (33–42), muted otherwise. Bottom legend.
- **Right column**: Target rings hit pattern (240×240) with X/Y/SD readout + AI-reflectie BracketFrame (T3, gradient: STRONG/IMPROVE/NEXT three-col grid) + user note card.

#### 2.3 · Wapen-detail — `WeaponDetail`
- **Left col** (320px): Weapon ID card with photo placeholder (16:10 aspect, accent SVG silhouette inside) + metadata key/value list + calibration panel (4-cell grid: KORREL · VIZIER · TREKKER · HANDGREEP).
- **Right col**: 4-stat row + Score trend card with full-width sparkline (760×140) + sessions table filtered to this weapon.

#### 2.4 · AI-coach — `AICoachView`
3-column layout:
- **Left rail** (260px): "Recente gesprekken" list with active state, mini timestamp.
- **Center thread** (flex 1, 28/40 padding): timestamp pill divider + alternating user/AI bubbles. User: right-aligned, accent-tinted, max 560px. AI: left-aligned with 28×28 avatar (`var(--panel-2)` bg + AI icon), max 620px. AI bubble can include a "chart attachment" panel with sparkline + label, or a "cta attachment" with primary + ghost buttons. Composer at bottom with chip suggestions.
- **Right context rail** (280px): "Context in gesprek" cards (sessie + wapen with "IN" badges) + "Voorgestelde doelen" checkboxes + privacy note.

#### 2.5 · Nieuwe sessie wizard — `NewSessionWizard`
Modal flow, currently rendered at step 3 ("Schoten loggen"). 720×640 modal with step indicator + numpad-style shot entry + live total / average. See `new-session-wizard.jsx`.

### 3 · Empty states (4 screens)
**File**: `empty-states.jsx`
- First-run welkom (onboarding 3-step card)
- Geen sessies — eerste-sessie nudge
- Geen wapens — with starter templates
- AI-coach · te weinig data — progress to 3 sessies

Pattern: centered hero illustration (large reticle or icon, `var(--accent)` at 20% opacity) + headline (display/md) + sub-text + primary CTA + tertiary action.

### 4 · Marketing landing page
**File**: `marketing.jsx`
**Size**: 1440×900 (page itself scrolls)

One long-scroll page. Sections in order:
1. **Sticky nav** (18×64 padding, blur backdrop, `var(--bg)cc`) — Wordmark + nav items + "Inloggen" + "Probeer gratis" CTA
2. **Hero** (88/64 padding): Two-col grid. Left: kicker pill + h1 64px "Je schietsessies, scherp in beeld." with accent 2nd line + sub-text + two CTAs + feature pills. Right: 420×420 hero visual = T2 ring (reticle 420) wrapping TargetRings, with 3 floating callouts (SCORE / GROEP / AI). Top-right corner has subtle large reticle watermark at opacity 0.07.
3. **Trust strip**: 24×64 padding, top+bottom 1px border, "Gebruikt door…" + 5 club names
4. **Features grid** (88/64): h2 44px + 3×2 cards. Each card: kicker (mono 10px 0.18em) + h3 (display 22px) + body + a demo widget. Cards have small corner accent (15×15 borderTop+borderRight on top-right — note: this is the original decorative element, NOT a full T3 BracketFrame). The WM-4 feature demo has a T4 "WM-4 OK" stamp.
5. **AI-coach deep dive** (96/64, top border): 2-col grid. Left: kicker + h2 + body + 4 check-marked features. Right: T3 BracketFrame around mock chat (header strip + alternating user/AI bubbles + sparkline attachment).
6. **Self-hosted CTA** (64/64, top+bottom border): 2-col. Left: kicker + h2 "Eén command, eigen instance." + body. Right: terminal mock (panel bg, mono 13px, traffic-light dots, 3 lines).
7. **Pricing** (88/64): centered intro + 3-card row. Middle card "Schutter" is primary (accent gradient bg, accent border, "POPULAIR" tag). Each card: kicker + price (44px display + sub) + divider + 4 check features + CTA button.
8. **Footer** (40/64, top border): mini Wordmark + version line + nav links

### 5 · Mobile companion (3 phones side-by-side)
**File**: `mobile.jsx`
**Size**: 1280×880 frame, each phone 360×780 inside iOS device chrome
- **Vandaag**: Greeting strip + hero "deze week" card (gradient bg + T1 watermark + 3-sessies large mono + weekly sparkline) + primary CTA "Nieuwe sessie loggen" + Recente sessies list + AI-tip card
- **Live loggen**: Live header strip with REC timer + Current score card (T1 centered watermark + huge accent score + avg/best line) + shot strip (visualized bars) + numpad (4-col mono grid) + sticky "Sessie afronden"
- **AI-coach**: Header strip + chat thread (user bubbles right + AI bubbles left with attachments) + composer bar with send button

All three use a shared **floating tab bar** at the bottom (6/16 padding, 22 radius, `var(--panel)d9` + 20px blur, 1px line border) with 4 tabs: Vandaag · Loggen · Coach · Meer. Active tab: accent-tinted bg + accent icon.

### 6 · Design tokens reference
**File**: `design-tokens.jsx` → `DesignTokens`
**Size**: 1440×1900
Shows every primitive — colors, type scale, spacing, radius, components, iconography — as a build-ready reference. Open this artboard fullscreen and print/screenshot if you want a hand-out.

### Alternatives (kept for reference)
**Files**: `field-journal.jsx`, `tactical-hud.jsx`
Two design directions that were NOT selected (Field Journal = editorial/serif-leaning journal layout; Tactical HUD = scope/optics readout with corner brackets and big mono everywhere). Implement only if explicitly asked — these are kept on the canvas so a stakeholder can compare.

---

## Components — what to build first

Build these as reusable primitives in your codebase. The HTML prototypes show them being reused everywhere.

### `<AimTrackLogo>` and `<Wordmark>`
- AimTrackLogo: takes `size` and `color`. Renders the SVG via CSS mask so color is freely swappable. Source SVG: `assets/aimtrack-logo.svg`.
- Wordmark: inline logo + "AimTrack" text. The "Track" suffix is accent-colored.

### `<Reticle>` (T1 building block)
- SVG with: circle (radius = 36% of size) + 4 N/S/E/W ticks outside (tickLen = 18% of size, gap = 6%) + optional center dot
- Used at large size + low opacity for watermarks

### `<ATMark>` (T4 building block)
- 100×100 viewBox, stroke 9px, square line caps
- Interlocked A+T monogram

### `<WatermarkBg palette size top right left bottom center opacity dot stroke>` (T1 utility)
- Wraps a Reticle in `position: absolute` with pointer-events:none
- `center: true` mode centers it via inset:0 + flex centering
- Host container needs `position: relative; overflow: hidden`

### `<BracketFrame palette children cornerSize cornerStroke cornerColor bordered panel rounded padding style>` (T3 utility)
- Renders a relative div with optional bg/border/radius/padding
- Adds 4 absolute-positioned corner brackets at -1 inset (so they overlap the border)
- Used wherever the AI / emphasis treatment is needed

### `<RingMedaillon palette fonts size label value sub color stroke>` (T2 utility)
- Reticle + centered text stack (label / big value / sub)
- Use sparingly: 1× per screen maximum

### `<MonogramStamp palette fonts label variant size corner color>` (T4 utility)
- ATMark + label in a small pill
- `variant: 'solid' | 'outline'` — solid for primary trust moments, outline as a softer "verified" indicator
- `corner: 'top-right' | 'top-left'` — absolutely positions at -10 top, 16 inset; otherwise inline

### `<TargetRings size hits accent dim ringStroke scoreLabels>`
- SVG: 8 concentric rings (10–3 score), inner crosshair (dashed), optional ring labels
- `hits`: array of `{x, y, r}` where x/y are -1..1 normalized within ~35% of max radius, r is the score (≥9.5 highlighted with halo)

### `<Spark data w h color fill strokeW>`
- Sparkline with optional area fill (gradient mask). Endpoint dot drawn at last point.

### Icons
20+ stroke-based icons defined in `shared.jsx` as `ICONS`. Bind via `<Icon d={ICONS.target} size sw stroke />`. Stroke-only, no fills, 24×24 viewBox, stroke-linecap=round, stroke-linejoin=round.

Names: target · crosshair · weapon · session · ai · spark · export · search · bell · add · arrow · up · down · filter · cal · shield · chat · dot · check · more

**No emoji anywhere.**

### Buttons

| Variant | Padding | Bg | Color | Border | Radius |
|---|---|---|---|---|---|
| Primary | 7×12 | `var(--accent)` | `var(--cta-text)` | none | 6px |
| Secondary | 7×12 | `var(--panel)` | `var(--text)` | 1px `var(--line)` | 6px |
| Ghost | 7×12 | transparent | `var(--text)` | 1px `var(--line)` | 6px |

All buttons: font-weight 600 (primary) / 500 (others), 12px, flex with 6px gap for icon + label.

### Badges

```
ok:      var(--accent)1f bg, var(--accent) text, var(--accent)33 border
pending: var(--warn)1a bg, var(--warn) text, var(--warn)33 border
neutral: var(--muted)14 bg, var(--muted) text, var(--line) border
```

All badges: 3×7 padding, 4px radius, mono 10px 0.08em letter-spacing, UPPERCASE.

### Cards

Default: `var(--panel)` bg + 1px `var(--line)` border + 8px radius + overflow hidden.
Card head: 14×16 padding, 1px bottom border, flex with icon + title + sub (right-aligned).
Card body: 16 padding.

---

## Interactions & behavior

- **Theme/palette switch**: live, no reload. CSS custom properties on `:root` swap.
- **Font preset switch**: same — sets `--at-display`, `--at-body`, `--at-mono` on `:root`.
- **Nav active state**: keyboard accessible (Tab + Enter). Filament gives this for free.
- **Table row hover**: bg lifts from `var(--panel)` to `var(--panel-2)`, 150ms.
- **Tweaks panel** (in the prototypes): a floating settings panel for live tweaking — NOT a production feature, only useful during design review. Don't ship it.

### AI-coach typing indicator
3 dots, pulse animation (1.2s infinite ease-in-out, opacity 1 → 0.3 → 1). Defined in `<style>` in `AimTrack Designs.html` as `@keyframes pulse`.

### Mobile tab bar
Floating with 20px blur backdrop. Active tab: bg `var(--accent)1f`, icon + label colored `var(--accent)`. Inactive: `var(--muted)`. Tap target ≥44px.

---

## State management

Per-screen state:

- **Sessies overview**: filters (date range, weapon, discipline), pagination
- **Sessie-detail**: shots[] (60 floats), series totals (derived), AI reflection (text + 3 categories: strong/improve/next), user note (mutable string)
- **Wapen-detail**: weapon record + sessions filtered to this weapon
- **AI-coach**: conversation thread (messages[] with role: user/ai/system + optional attachments: chart/cta)
- **Wizard**: current step (1..N) + accumulated session data
- **Mobile live**: in-progress shots[] (append-only), session start time

Persist to backend via Filament resources + Laravel models. Audio/photo attachments (if added later) go to S3-compatible storage.

---

## Assets

- **Logo**: `assets/aimtrack-logo.svg` — already in your repo as the official logomark. Used as a CSS-mask source so color is dynamic.
- **No other static images** — every graphic in the design is procedurally drawn (SVG via React) using the design tokens. The weapon-detail "photo" placeholder is a stylized SVG silhouette and should be replaced with user-uploaded photos in production.

---

## Files in this bundle

Open `AimTrack Designs.html` in a browser to see the full design canvas. The supporting files are loaded via `<script type="text/babel">` tags:

- `AimTrack Designs.html` — entry point, palette/font defs, root App component
- `design-canvas.jsx` — design-canvas web component (pan/zoom, fullscreen, drag-reorder). **Internal tooling only — don't port**.
- `tweaks-panel.jsx` — Tweaks panel + controls. **Internal tooling only — don't port**.
- `shared.jsx` — AimTrackLogo · Wordmark · TargetRings · Spark · Icon · ICONS · mock data (SESSIONS · WEAPONS · TREND_30D · RING_HITS)
- `logo-system.jsx` — **The 4 utility components: `WatermarkBg` · `BracketFrame` · `RingMedaillon` · `MonogramStamp` + `LogoSystemReference` page.** ★
- `logo-treatments.jsx` — original 4 T1–T4 comparison cards
- `a-styles.jsx` — shared styles for Range Console screens (sidebar, topbar, panels, stats, badges)
- `range-console.jsx` — RangeConsole (sessies overview)
- `a-screens.jsx` — SessionDetail · WeaponDetail · AICoachView
- `new-session-wizard.jsx` — NewSessionWizard modal
- `empty-states.jsx` — 4 empty/first-run states
- `marketing.jsx` — MarketingLanding (long-scroll page)
- `mobile.jsx` — MobileScreens (Vandaag · Live · Coach phones)
- `ios-frame.jsx` — iOS device bezel wrapper (used by mobile.jsx)
- `design-tokens.jsx` — DesignTokens reference card
- `field-journal.jsx` — Alternative direction B (not chosen)
- `tactical-hud.jsx` — Alternative direction C (not chosen)
- `assets/aimtrack-logo.svg` — source logo

---

## Implementation order (recommended)

1. **Tokens first** — wire `tokens.css` (or `tailwind.config.js`) with colors / type / spacing / radius. Verify a sample button renders identically to the prototype.
2. **Logo system utilities** — port `WatermarkBg`, `BracketFrame`, `MonogramStamp`, `RingMedaillon` as your first 4 components. Everything else uses them.
3. **Shell** — sidebar + topbar layout used by every Range Console screen.
4. **Sessies overview** — wire data + table + stats; this is the daily landing page.
5. **Sessie-detail** — most complex screen, lots of visualizations. Build the BracketFrame AI-card pattern thoroughly here, then reuse.
6. **Remaining Range Console screens** — wapen-detail, AI-coach, wizard, empty states.
7. **Marketing landing** — separate Blade view or static page.
8. **Mobile** — separate codebase (PWA or native).

---

## Open questions for the developer

- **Filament version**: 3.x is assumed. If you upgrade to 4.x, verify the panel theming API hasn't changed.
- **AI backend**: the prototype assumes a streaming chat endpoint that returns conversational text + structured "attachments" (chart data, CTA buttons). The exact API contract is not in scope of this design — coordinate with backend engineering.
- **WM-4 export**: the format is prescribed by Dutch shooting-sport regulations — implement the PDF / CSV layout per spec, the UI just kicks off the job.
- **Mobile**: not implemented in Filament. Recommend a PWA (Capacitor + Vue or Inertia) if a native app is out of scope.

Good luck. 🎯 — Marc & the AimTrack design pass, mei 2026.

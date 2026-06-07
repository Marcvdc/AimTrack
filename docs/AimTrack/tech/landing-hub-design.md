# Landing Hub Design Spec
Status: Drafted 2026-01-08
Related Plan: `docs/plans/landing-hub-plan.md`
Issue: AIMTRACK-landing-hub

## 1. Experience Goals
- Position AimTrack als "schiethub": direct duidelijk waarvoor het platform is, met nadruk op veiligheid, sessielogging en AI-coach.
- Maak vertrouwen door KNSA-referenties, duidelijke CTA's en een beknopte handleiding.
- Tablet-first: interface voelt aan als een dashboard dat je naast de baan kunt gebruiken.

## 2. Visual Language
- **Type**: `Space Grotesk` voor headings, `Manrope` voor body (contrast met bestaande Inter). Grote titels (clamp(2.5rem, 4vw, 4rem)), body 1.1rem.
- **Kleurpalet**:
  - Base: `#040711` → `#0c1428` gradient (radial) + noise overlay.
  - Accent 1 (cta): `#64f4b3` (mint) met soft glow.
  - Accent 2 (cards/badges): `#7a8cff` (cool indigo) voor highlights.
  - Neutral text: `#e3ecff`, mutet text `#94a3c5`.
- **Shapes**: gebruik glassmorphism cards (background rgba(13,20,41,.65), border 1px rgba(255,255,255,.08), blur 18px). Achtergrond orbs (absolute divs) voor dynamiek.
- **Motion**: hero items slide-up (150ms delay), cards hover tilt (translateY(-6px)), CTA ripple on focus.

## 3. Layout & Sections
1. **Hero / Mission**
   - Left column: badge "Schiethub" + h1 + subcopy (veilig loggen, wapens beheren, AI-coach). Buttons: primary `Inloggen /admin/login`, secondary `Handleiding bekijken` (#handleiding).
   - Right column: data widget (shots heatmap tile + stats list) om product te tonen.
   - On tablets max 2 kolommen; <768px stacked.
2. **Key Features grid**
   - 4 cards (Sessies & ROOS, Wapenbeheer, AI-coach, Export & Rapportage). Ieder icon (SVG inline) en bullet(s).
3. **KNSA Referenties**
   - 3 highlight cards met titel, samenvatting, link-out (target `_blank` + `rel="noreferrer"`).
     1. Veilig Wapenbezit — 10 veiligheidsregels + callout.
     2. Checklist Baanveiligheid — pre-sessie checklist.
     3. Wedstrijdkalender & Reglementen — training vs. competitie.
   - Zet badge "Bron: KNSA".
4. **Handleiding (Quickstart)**
   - Section id `handleiding`. 3 mini-kaarten met nummering.
     - "Start je eerste sessie": account → wapen toevoegen → sessie starten.
     - "Veiligheidscheck": herhaal KNSA check, log baancondities.
     - "Ontdek AI-coach": voorbeeldvraag + tip.
5. **Add-ons / Extra Modules**
   - Present drie uitbreidingsopties zodat PO kan kiezen wat actief blijft:
     - Live statistieken (total sessions, accuracy trend).
     - Community / verhalen (quote carrousel, link naar blog).
     - Roadmap & partner CTA (join beta, KNSA partners). Toon placeholder data.
6. **Footer CTA**
   - Herhaal login CTA (link `/admin/login`) + secundaire knoppen naar KNSA-content/handleiding. Geen demo-link nodig.

## 4. Responsive Behavior
- Hero grid `grid-template-columns: repeat(auto-fit,minmax(280px,1fr))`.
- Cards `flex-direction: column`, gap 1rem; on mobile full width.
- CTA's min height 52px, 18px font.
- Sticky quick CTA bar (floating button) op <640px: fixed bottom with login button.

## 5. Accessibility & Content Notes
- Contrast ratio >= 4.5:1 (mint/indigo on dark). Borders lighten.
- Use semantic headings (h1 hero, h2 for sections, h3 for cards) en `aria-labels` op resource links.
- External KNSA links: `rel="noreferrer noopener"` + icon.
- Copy tone: coachend, Nederlands, korte zinnen.

## 6. KNSA Artikel Referenties (voor uitvoering)
1. **Veilig Wapenbezit** – https://www.knsa.nl/veiligheid/veilig-wapenbezit (10 regels). Samenvatting in card.
2. **Checklist Baanveiligheid** – https://www.knsa.nl/veiligheid/checklist-baanveiligheid (pre-flight checklist).
3. **Wedstrijdkalender & Reglementen** – https://www.knsa.nl/wedstrijden/reglementen (trainingsdoelen afstemmen).
4. **Opleidingen & Handreikingen** (optioneel) – https://www.knsa.nl/opleidingen (voor ambitie blok).

## 7. Handleiding Card Content (voorbeeld)
1. **Start je eerste sessie**
   - Stap 1: Log in → Dashboard
   - Stap 2: Voeg wapen & baan toe
   - Stap 3: Start sessie en registreer schoten
2. **Veiligheidscheck**
   - Checklist knop (link KNSA) + toggle "baan ok?"
3. **AI-coach tips**
   - Voorbeeldvraag: "Hoe verbeter ik mijn 25m vrij pistool groep?" -> AI highlight.

## 8. Open items
- Definitieve brand assets (logo SVG?).
- Foto/illustratie keuze (optioneel). In tussentijd gradient orbs.

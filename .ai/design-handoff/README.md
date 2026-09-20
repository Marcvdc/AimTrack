# CODING AGENTS: READ THIS FIRST

This is a **handoff bundle** from Claude Design (claude.ai/design).

A user mocked up designs in HTML/CSS/JS using an AI design tool, then exported this bundle so a coding agent can implement the designs for real.

## What you should do — IMPORTANT

**Read the chat transcripts first.** There are 1 chat transcript(s) in `aimtrack/chats/`. The transcripts show the full back-and-forth between the user and the design assistant — they tell you **what the user actually wants** and **where they landed** after iterating. Don't skip them. The final HTML files are the output, but the chat is where the intent lives.

**Read `aimtrack/project/AimTrack Designs.html` in full.** The user had this file open when they triggered the handoff, so it's almost certainly the primary design they want built. Read it top to bottom — don't skim. Then **follow its imports**: open every file it pulls in (shared components, CSS, scripts) so you understand how the pieces fit together before you start implementing.

**If anything is ambiguous, ask the user to confirm before you start implementing.** It's much cheaper to clarify scope up front than to build the wrong thing.

## About the design files

The design medium is **HTML/CSS/JS** — these are prototypes, not production code. Your job is to **recreate them pixel-perfectly** in whatever technology makes sense for the target codebase (React, Vue, native, whatever fits). Match the visual output; don't copy the prototype's internal structure unless it happens to fit.

**Don't render these files in a browser or take screenshots unless the user asks you to.** Everything you need — dimensions, colors, layout rules — is spelled out in the source. Read the HTML and CSS directly; a screenshot won't tell you anything they don't.

## Bundle contents

- `aimtrack/README.md` — this file
- `aimtrack/chats/` — conversation transcripts (read these!)
- `aimtrack/project/` — the `Aimtrack` project files (HTML prototypes, assets, components)

## Correctie op dit bundel (2026-09-20, issues #131, #132 en #162)

Dit bundel is het bronmateriaal waar de marketing- en app-copy uit is overgenomen, dus
onjuiste beweringen hierin komen via een volgende ronde zo weer terug. Twee daarvan zijn
gecorrigeerd in `project/` en in de beide afgeleide kopieën:

- **Geen WM-4-conformiteit.** WM-4 is het verlofdocument dat de korpschef afgeeft, geen
  voorgeschreven exportformaat en geen standaard waaraan software kan voldoen. De export is
  een gewoon CSV- of PDF-overzicht van je eigen sessies voor je verenigingsadministratie.
  Het monogram-stempel (T4) is daarmee een statusmarkering (`REFLECTIE OK`, `EXPORT OK`) en
  geen validatie- of vertrouwenssignaal. Zie ook de noot boven `chats/chat1.md`.
- **De AI-coach stuurt wel data naar buiten.** De regel "alles draait lokaal, je data
  verlaat de server niet" was onjuist. De app en de database draaien op de eigen server,
  maar voor een AI-antwoord gaan de vraag, de sessiecontext en de wapengegevens (inclusief
  serienummer en opslaglocatie) naar Anthropic (api.anthropic.com). Zonder API-key gebeurt
  er niets en de hele functie is uit te zetten met `FEATURE_AIMTRACK_AI`.

Ook weggehaald omdat de dienst niet bestaat: de "NL-cloud" op de privacy-kaart en de
keuringsbrief op de wapenkaart.

**Nog open, bewust niet door de agent beslist:** het bundel bestaat uit drie kopieën van
hetzelfde materiaal (`project/`, `project/design_handoff_aimtrack/` en
`project/uploads/AimTrack (Remix)/`). Alle drie zijn nu gecorrigeerd, maar ze blijven uit
de pas lopen. Houd `project/` aan als bron en ruim de andere twee op; dat is een keuze voor
de eigenaar van het ontwerp. Het prijsblok in `marketing.jsx` noemt daarnaast nog een
gehoste dienst ("Hosted bij AimTrack NL") en betaalde abonnementen die niet bestaan; dat is
een productbeslissing en valt buiten deze issues.

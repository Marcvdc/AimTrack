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
  verlaat de server niet" was onjuist, en "alleen de AI-coach stuurt gegevens naar buiten"
  ook: een beheerder kan daarnaast mail, Sentry en een offsite backup inrichten. De app en
  de database draaien op de eigen server, maar voor een AI-antwoord (reflectie,
  wapeninzicht en de chat op de coachpagina) gaan de vraag en de gegevens die de coach
  erbij haalt naar Anthropic. Welke gegevens dat zijn, staat in `docs/user/README.md`
  onder "Waar je gegevens staan" en komt uit `app/Support/Ai/AiPrivacyNotice.php`;
  schrijf die opsomming hier niet over, want dan loopt hij weer uit de pas.
- **Geen "verified".** Het T4-stempel had `VERIFIED` als label en heette een
  "verification badge". Dat is weg uit `logo-system.jsx`, `logo-treatments.jsx`, de
  artboard-subtitel in `AimTrack Designs.html` en de componentbeschrijving in
  `design_handoff_aimtrack/README.md`.

Ook weggehaald omdat de dienst niet bestaat: de "NL-cloud" op de privacy-kaart en de
keuringsbrief op de wapenkaart.

**Het bronproject moet ook worden gecorrigeerd.** Dit bundel is een export uit Claude
Design. Alle correcties hierboven staan alleen in de geëxporteerde bestanden; het project
in Claude Design zelf is niet aangepast, want daar kan de agent niet bij. Een volgende
export zet de claims dus terug, tenzij iemand ze eerst in Claude Design weghaalt. Als
vangnet faalt `tests/Feature/DesignHandoffClaimsTest.php` zodra een van de bekende claims
weer in de `.jsx`- of `.html`-bestanden van dit bundel staat.

**Nog open, bewust niet door de agent beslist:**

- Het bundel bestaat uit drie kopieën van hetzelfde materiaal (`project/`,
  `project/design_handoff_aimtrack/` en `project/uploads/AimTrack (Remix)/`). Alle drie
  zijn gecorrigeerd, maar ze blijven uit de pas lopen. Houd `project/` aan als bron en ruim
  de andere twee op; dat is een keuze voor de eigenaar van het ontwerp.
- Het prijsblok in `marketing.jsx` noemt een gehoste dienst ("Hosted bij AimTrack NL") en
  betaalde abonnementen die niet bestaan, en daarbinnen ook "Eigen AI-model (optioneel)",
  "SSO via KNSA" en "Batch-export". Geen van die drie bestaat in de app. Een prijsmodel
  aanpassen is een productbeslissing en valt buiten deze issues.

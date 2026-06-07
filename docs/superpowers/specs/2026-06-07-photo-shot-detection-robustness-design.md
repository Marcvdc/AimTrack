# Foto→schoten robuustheid (plakken-per-beurt) — Design Spec

**Status:** DRAFT (door gebruiker goedgekeurd; spec-review volgt)
**Datum:** 2026-06-07
**Issue:** [GH-55](https://github.com/Marcvdc/AimTrack/issues/55)
**Worktree / branch:** `aimtrack-55` / `feature/55`
**Vervolg op:** `docs/superpowers/specs/2026-06-06-photo-shot-detection-design.md` (v1, hybride CV + Claude)

---

## 1. Context & aanleiding

De v1-pipeline (homografie → CV-kandidaten → Claude-discriminatie → reconcile → discipline-correcte
scoring) werkt op **schone** foto's, maar faalt op echte schietbaan-roosjes. Concreet geval (gebruiker,
2026-06-07): één foto met **twee roosjes over elkaar** (klein zwart op groot), **honderden lichte
plakkers** over oude treffers, **tape** over scheuren en een **versleten** zwart vlak. Resultaat:
kalibratie-afwijking 29.8mm (limiet voor zwaar gebruikte rozen), Claude `overall_confidence` 0.4, en
de **aantal-constraint forceerde twijfelgevallen (plakkers) als schot**.

### Workflow-besluit (bepaalt de hele aanpak)
De gebruiker plakt **tussen elke beurt** de gaten dicht. Daardoor toont elke beurt-foto **alleen de
verse, donkere gaten** van die beurt; alle oude treffers zitten onder **lichte** plakkers. Het
kernsignaal wordt dus simpel: **vers gat = donker, oude gaten = licht (plakker)**. Dat maakt
discriminatie haalbaar (de chaos-foto van net was *niet* tussendoor geplakt en valt buiten de happy
path).

### Lat (besloten)
"Werkt het" = **goede detectie ÉN vlotte correctie**: detectie doet ~90%, de gebruiker corrigeert de
rest in een paar klikken. Strategie: **incrementeel verharden** van de werkende v1-hybride (geen
vision-first herbouw).

---

## 2. Doelen & niet-doelen

### Doelen
1. Detectie afgestemd op **verse donkere gaten** op een plakker-rijke roos; geen plakkers/cijfers
   meer als schot.
2. Twijfelgevallen worden **niet meer geforceerd** om het ingevulde aantal te halen.
3. **Vlotte correctie-UI**: versleepbare markers + verwijderen/toevoegen + een **"Bevestigd"-knop**
   die `needs_review` wegzet.
4. **Kalibratie-robuustheid**: duidelijke melding bij slechte uitlijning + handmatige fallback.
5. **Validatie op échte foto's** om prompt/drempels te ijken.

### Niet-doelen
- Vision-first herbouw van de detectie (afgewezen: strategie B).
- Before/after-foto-differencing (workflow is plakken-per-beurt, dus niet nodig).
- Het multi-target A3-vel (blijft uitgesteld).
- Detectie kloppend krijgen op niet-geplakte, volgelopen rozen (buiten de gekozen workflow).

---

## 3. Architectuur

Blijft de v1-hybride. Wijzigingen zijn gelokaliseerd per fase:

```
python-service:  claude_detector (prompt)  ·  reconcile (confidence-drempel)
                 + optioneel kalibratie-endpoint met handmatige referentiepunten
Laravel/Livewire: SessionShotBoard (moveShot, confirmTurnReview)  ·  blade (sleepbare
                 markers, Bevestigd-knop, handmatige-kalibratie-UI)
validatie:       tools/validate_detection.py op echte gelabelde foto's
```

---

## 4. Fasen (elk los te leveren)

### Fase 1 — Detectie scherper (klein, hoogste waarde)
- **Claude-prompt** (`_system_prompt` in `claude_detector.py`) afstemmen op de plakken-per-beurt-
  realiteit: *oude treffers zijn dichtgeplakt met lichte plakkers (GEEN schoten); tel alleen verse
  kogelgaten (donkere perforaties op papier; lichte doorschijn waar vers door het zwart is geschoten);
  negeer plakkers, gedrukte ringcijfers, ringlijnen, scheuren en tape.*
- **Confidence-drempel in `reconcile`** (nieuwe `settings`-waarde, bijv. `AIMTRACK_MIN_SHOT_CONFIDENCE`,
  default ~0.4): holes onder de drempel of `kind="uncertain"` met lage zekerheid worden **niet**
  meegeteld om `expected_shot_count` te halen. Liever minder, zekere schoten + `needs_review`
  ("minder gevonden dan ingevuld — controleer") dan een plakker als schot.
- Tests (pytest): reconcile laat sub-drempel-holes vallen i.p.v. ze te forceren; pipeline-gedrag.

### Fase 2 — Correctie-UI
- Schoten als **versleepbare markers** op de canvas (`SessionShotBoard`):
  - **slepen → verplaatsen**: `moveShot(int $shotId, float $x, float $y)` herberekent ring/score via
    `ShotScoringService` en zet `source='photo_corrected'`.
  - **klikken op marker → verwijderen** (bestaande delete-flow), **lege plek → toevoegen** (bestaande
    `recordShot`).
- **"Bevestigd"-knop** per beurt: `confirmTurnReview(int $turnIndex)` zet
  `session_turn_analyses.needs_review = false`; badge verdwijnt.
- Tests (Pest/Livewire): `moveShot` herberekent ring/score + `source`; `confirmTurnReview` wist de vlag.

### Fase 3 — Kalibratie-robuustheid
- **Guard** (grotendeels aanwezig): hoge rms / lage confidence → duidelijke `needs_review`-reden
  ("onnauwkeurige uitlijning — maak een rechtere, scherpere foto").
- **Handmatige fallback**: bij slechte/gefaalde auto-kalibratie markeert de gebruiker het **centrum +
  de ring-1-rand** (2–3 klikken) op de foto; daaruit een eenvoudige homografie/schaal → detectie
  opnieuw. Nieuw mini-endpoint (`POST /api/v2/analyze-target` variant met `reference_points`) + UI.
  Grootste stuk; mag als laatste.

### Fase 4 — Validatie op échte foto's
- Gebruiker levert een paar echte plakken-per-beurt-foto's per discipline met bekend aantal/ringen.
- `tools/validate_detection.py` meet **telling-accuratesse, ring-accuratesse, plakker-false-positives**.
- Prompt + confidence-drempel ijken tot het goed genoeg is. Iteratief.

---

## 5. Data flow & datamodel

- Geen nieuwe tabellen nodig. `session_turn_analyses.needs_review` wordt door `confirmTurnReview`
  op `false` gezet. Gecorrigeerde schoten krijgen `source='photo_corrected'` (waarde bestaat al in de
  UI-filters).
- De confidence-drempel is een `settings`-waarde in de python-service (env-configureerbaar).

## 6. Foutafhandeling
- Detectie degradeert naar `needs_review` met concrete reden (bestaand, v1).
- Correctie is **altijd** beschikbaar (ook als detectie 0 schoten gaf).
- Kalibratie valt terug op de handmatige referentiepunten-flow bij slechte auto-uitlijning.

## 7. Testen
- **pytest**: reconcile confidence-drempel (junk valt weg, geen forcering); prompt-string bevat de
  fresh-hole/ignore-plakkers-instructies (smoke).
- **Pest/Livewire**: `moveShot` (ring/score + source), `confirmTurnReview` (vlag weg), sleep-interactie
  via de component-actie.
- **Validatie-harness**: handmatig, op echte foto's (Fase 4).

## 8. Besluiten-log
1. Workflow: **plakken tussen elke beurt** → per-beurt-foto = alleen verse donkere gaten.
2. Lat: **goede detectie ÉN vlotte correctie** (~90% auto + 2-kliks-correctie).
3. Strategie: **A — incrementeel verharden** (geen vision-first herbouw).
4. Fase-volgorde: 1 (detectie) → 2 (correctie) → 3 (kalibratie) → 4 (validatie); elk los leverbaar.
5. Geen before/after-differencing (niet nodig bij plakken-per-beurt).

## 9. Open punten voor het plan
- Exacte default voor `AIMTRACK_MIN_SHOT_CONFIDENCE` (ijken in Fase 4).
- UX van het slepen (raster/snap? feedback tijdens slepen?) — detail in het plan.
- Vorm van de handmatige-kalibratie-referentiepunten (2 punten = centrum+rand vs 3 punten) — detail in
  het plan.
- Acceptatiedrempels per discipline voor de validatie-harness.

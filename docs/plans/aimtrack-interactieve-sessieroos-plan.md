# Interactieve Sessieroos Plan
Status: APPROVED  
Auteur: Cascade AI  
Datum: 2026-01-07

## 1. Context & Aanleiding
- Coaches en schutters willen per sessie exact registreren waar elk schot op de roos terechtkwam, inclusief de beurt waarin het plaatsvond.
- De huidige sessie-flow registreert wel scores, maar mist een visuele/ruimtelijke input waardoor nauwkeurige analyses en totalen ontbreken.
- Een interactieve roos binnen de sessie-UI moet schotten vastleggen, punten per ring berekenen en totals per sessie tonen.

## 2. Doelen
1. Gebruiker kan binnen een sessie per schot klikken op een interactieve roos om de locatie en daarmee de score vast te leggen.
2. Alle schoten worden opgeslagen per sessie én per beurt, inclusief volgorde, ring-score en (x,y)-coördinaten.
3. Sessie-overzicht toont automatisch de totalen: som van punten, aantal schoten per beurt, gemiddelden en eventuele heatmap.

## 3. Scope & Grenzen
**In scope**
- UI-component (Filament/Laravel) voor interactieve target (mouse/touch input, visuele feedback, undo).
- Backend-endpoints/Livewire-handlers om schotdata te serialiseren (coördinaten, ring, timestamp) en persistente opslag (nieuwe tabel of JSON-kolom).
- Aggregaties per sessie en beurt (totaal score, gemiddelde, max/min, count) + presentatie in sessie-detail.
- Documentatie (docs/AimTrack/index.md recent updates, nieuwe sectie in `docs/user/sessions.md`).
- Pest-tests voor scoring-logic + eventuele Livewire/Feature tests.
- Linting (Laravel Pint) en datakwaliteit-controles.

**Uit scope**
- Realtime multiplayer of coach-liveview.
- Externe hardware-integraties (elektronische targets).
- Historische data backfill (optioneel documenteren, geen migratie verplicht in deze iteratie).

## 4. Constraints & Assumpties
- Frontend blijft binnen bestaande tech (Laravel + Filament/Livewire). Geen externe JS frameworks tenzij al aanwezig.
- Coördinaten worden genormaliseerd (0..1) om toekomstige resoluties te ondersteunen.
- Beurten hebben variabel aantal schoten; UI moet dynamisch kunnen toevoegen/verwijderen.
- Scoring volgt standaard roos: ring 10 (bull) t/m 1; buiten doel = 0. Afstand naar center bepaalt ring (configurabel).
- Geen PII in logs, API timeouts volgens C-API-Timeouts constraint.

## 5. Aanpak (Stappen)
1. **Analyse & Datastructuur**
   - Inventariseer huidige sessie- en schotmodellen; bepaal of nieuwe `shots` tabel of JSON array nodig is.
   - Definieer database migratie (sessie_id, beurt_index, schot_index, x, y, ring, score, metadata).
   - Bepaal services om businesslogica te scheiden (Controller mag geen directe queries).
2. **Scoring Service**
   - Bouw dedicated service die van (x,y) -> ring -> score berekent, incl. validatie & retries voor API-calls (n.v.t. maar structureel).
   - Schrijf Pest-tests (unit) voor ringbepaling en aggregator (sum/avg per sessie). Doel: ≥90% coverage van nieuwe services.
3. **Interactieve UI**
   - Maak component (Livewire/Filament widget) met SVG/canvas roos.
   - Implementeer klik/touch-handling, toont markers, laat gebruiker schoten per beurt beheren (toevoegen, verplaatsen, verwijderen).
   - Koppel aan backend-actions voor persistente opslag (debounce, validatie).
4. **Aggregaties & Rapportage**
   - Update sessie-detail view: tabel per beurt + totaalblok + grafische samenvatting (bijv. heatmap overlay of lijst).
   - Voeg export (JSON/CSV) hooks toe indien sessies nu exporteerbaar moeten zijn (optioneel configuratie in plan-appendix).
5. **Validatie, Docs & QA**
   - Schrijf scenario-tests voor hele workflow (sessie aanmaken, schoten loggen, totals checken).
   - Run Pint + Pest; los failures op vóór afronding.
   - Documenteer gebruik in `docs/user/sessions.md` (instructies, hints) en noteer wijziging in `docs/AimTrack/index.md` (Recent).

## 6. Acceptatiecriteria
1. Een gebruiker kan binnen een sessie minimaal 3 beurten met variabele aantallen schoten toevoegen en elke klik resulteert in een persistente shot-entry met ring-score.
2. Totale sessiepunten = som van alle schoten en wordt realtime bijgewerkt in de UI, inclusief per-beurt totalen en gemiddelden.
3. Pest-test suite bevat minstens één unit test voor scoringservice en één feature test voor sessie-flow (aanmaken schoten -> totals) en draait groen.
4. Documentatie beschrijft de nieuwe functionaliteit (user guide) en architectuurwijzigingen staan gelogd onder `docs/AimTrack/index.md`.

## 7. Afhankelijkheden & Vragen
- Bevestiging welk UI-framework (Filament resource vs. custom Livewire page) gebruikt moet worden voor de sessiepagina.
- Nodig: beslissen of bestaande sessie-export uitgebreid moet worden met shotdetails.
- Nodig: requirements voor maximum schoten per sessie (limieten voor performance).
- Eventuele analytics/coach-AI hooks gewenst?

# Landing Page Data & Docs Plan
Status: APPROVED (2026-01-24, per PO)  
Auteur: Cascade AI  
Datum: 2026-01-24  
Issue: GH-36  

## 1. Context & Aanleiding
- De publieke landingpage (`resources/views/welcome.blade.php`) toont reeds statische KPI-kaarten (sessies deze maand, gemiddelde score, AI-reflecties) en demo-componenten zoals heatmap en KNSA-blokken.
- PO wil dat deze cijfers voortaan live uit de database komen (algemene totalen, geen individuele gebruikersdata) zodat bezoekers actuele impact zien.
- Er staan placeholders voor KNSA-referenties; links moeten gecontroleerd en aangevuld worden met actuele bronnen vanaf knsa.nl.
- Alle bestaande landingcomponenten moeten behouden blijven; taak is om data en verwijzingen up-to-date te maken.

## 2. Doelen
1. Lever realtime (of dagelijks geüpdatete) waarden voor de bestaande landingpage-statistieken via backend queries/caching.
2. Zorg dat KNSA-linkkaarten dynamisch gevoed worden vanuit een centrale configuratie zodat wijzigingen eenvoudig zijn.
3. Houd de bestaande HTML-structuur en visuele stijl intact terwijl data en links correct worden gemaakt.
4. Documenteer data-herkomst, cachingstrategie en onderhoudsflow voor KNSA-links.

## 3. Scope & Grenzen
**In scope**
- Bepalen van de KPI-definities die al op de landing staan (sessies deze maand, gemiddelde score, aantal AI-reflecties) en toevoegen van eventuele extra aggregates die in de HTML genoemd worden.
- Implementeren van een server-side data-provider (bijv. controller/view-model) die totals en gemiddelden ophaalt uit Sessions, SessionWeapons, AiReflection e.d., met caching (daily bust) om performance te garanderen.
- Configureren van een centralized array/configbestand voor KNSA-links; controller geeft dit door aan view.
- Toevoegen van tests (Pest feature test) die landingpage-render controleert incl. voorbeelddata.
- Documentatie-update in docs/AimTrack/index.md (Recent) met uitleg over landing metrics en KNSA-linkbeheer.

**Uit scope**
- Nieuwe designcomponenten of herontwerp van de landing; layout en tekst blijven hetzelfde.
- User-by-user personalisatie of login-gebaseerde stats (alleen globale aggregaties).
- Queue/Filament admin metrics (valt buiten landing).

## 4. Constraints & Assumpties
- Metrics moeten uitsluitend aggregated gegevens tonen (bijv. totaal sessies deze maand = `Sessions` filtered by date); geen individuele usernamen of PII.
- Data mag maximaal 1 uur oud zijn; caching via `Cache::remember` of view composers is toegestaan om queryload te beperken.
- Landingpage moet blijven laden zonder databasefouten; bij lege data moeten fallback-waarden (0 of "n.v.t.") getoond worden.
- KNSA-links moeten afkomstig zijn van publieke pagina's op https://www.knsa.nl/ en openen in nieuw tabblad met `rel="noreferrer noopener"`.
- Geen extra JavaScript frameworks; blijf bij huidige blade + inline CSS.

## 5. Aanpak (Stappen)
1. **Metric definitie & queryontwerp**
   - Samenstellen van aggregaties: aantal sessies in lopende maand, gemiddelde `score` (bron?), aantal `AiReflection` records in 30 dagen, etc. Valideer dat de benodigde velden bestaan.
   - Bepaal cachingstrategie (bijv. `Cache::remember('landing.stats', 3600, fn () => ...)`).
2. **Controller/view modeling**
   - Introduceer dedicated controller (bijv. `LandingPageController`) of route closure die data ophaalt en aan view doorgeeft i.p.v. hardcoded waarden.
   - Voeg view-model structuren toe voor heatmap demo/fallbacks.
3. **KNSA-link configuratie**
   - Maak config of data file met lijst van KNSA-links (titel, beschrijving, URL). Documenteer hoe te updaten.
   - Controller leest config en levert array aan view; view blijft ongewijzigd qua markup.
4. **Tests & QA**
   - Schrijf Pest feature test die seed-data maakt, landing bezoekt en controleert dat aggregaties/links zichtbaar zijn.
   - Voeg unit test toe voor data-provider indien losgekoppeld.
5. **Docs & onderhoud**
   - Update docs/AimTrack/index.md (Recent) met samenvatting van metrics + beheer.
   - Vermeld KNSA-link lijst + onderhoudsflow in docs/AimTrack/domain of tech.
6. **Review & STOP-check**
   - Run pint & pest; controleer caching fallback scenario's.
   - Laat plan reviewen; na APPROVED pas bouwen.

## 6. Acceptatiecriteria
1. Landingpage toont minimaal de bestaande drie metrics met live data uit de database en herlaadt zonder hardcoded cijfers.
2. KNSA-referentieblok wordt gevoed via configureerbare lijst; wijzigingen vereisen geen Blade-edit.
3. Caching/fallback gedrag is beschreven én getest (bijv. test dat bij lege dataset "0" wordt weergegeven).
4. Documentatie vermeldt welke queries/statistieken worden gebruikt en hoe links beheerd worden.

## 7. Afhankelijkheden & Open Vragen
- Bevestigde metrics staan al in HTML; double-check of "Gem. score" gebaseerd is op bestaand veld (anders bepalen definitie).
- Welke databasekolom levert "AI reflecties"? (waarschijnlijk `ai_reflections` tabel) → bevestigen mapping.
- Zijn er additionele KNSA-links gewenst naast de drie bestaande? (default: huidige selectie van knsa.nl overnemen.)
- Eventuele behoefte aan featureflag om live stats uit te zetten bij storing?

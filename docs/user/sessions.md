# Sessies

Handleiding voor het registreren en beheren van schietsessies.

## Nieuwe sessie aanmaken
1. Open **Sessies** → **Nieuwe sessie** in Filament.
2. Vul basisgegevens: datum, baan/vereniging, locatie. Voeg eventueel weersinformatie of tijdstippen toe indien beschikbaar.
3. Voeg een korte **handmatige reflectie** of ruwe notitie toe (optioneel).
4. Voeg **Wapen × afstand** entries toe via de repeater:
   - Kies wapen
   - Afstand (m)
   - Aantal schoten
   - Munitie(type/kaliber)
   - Optioneel score/groepering/afwijking
5. Upload bijlagen (foto’s, kaarten, PDF). Deze worden aan de sessie gekoppeld.
6. Opslaan.

## Bekijken en bewerken
- In de tabel zie je datum, locatie, gekoppelde wapens, aantal entries en AI-status.
- Klik op een sessie om details te zien. Je kunt entries/bijlagen toevoegen of aanpassen en opnieuw opslaan.

## AI-reflectie
- Op de detailpagina: klik **Genereer AI-reflectie**.
- De queue-job vult `ai_reflections` met vier velden: samenvatting, wat ging goed, wat kan beter, focus.
- Status (pending/succeeded/failed) en errors zijn zichtbaar; bij mislukking kun je opnieuw genereren.

## Bijlagen
- Uploads worden opgeslagen via Laravel storage. Downloaden kan vanuit de sessiedetail of attachments-lijst.
- Bewaar alleen materialen die je zelf mag delen; verwijder bijlagen die niet langer nodig zijn.

## Filters
- Gebruik periodefilter, locatie/baan filter en wapenfilter om snel sessies terug te vinden.

## Tips
- Voeg na elke sessie direct een handmatige reflectie toe; AI-uitvoer wordt beter met duidelijke context.
- Noteer afwijkingen (bijv. “trekkerfout links hoog”) zodat trends zichtbaar worden in AI-trends per wapen.

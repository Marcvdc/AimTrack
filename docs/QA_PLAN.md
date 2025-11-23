# QA Smoke Plan – AimTrack

## Doel
Bevestigen dat de basisflows van AimTrack werken: gebruikers aanmaken, wapens registreren, sessies loggen (met bijlagen), AI-acties triggeren en exports genereren. We toetsen alleen MVP-flows zoals beschreven in docs/PLAN.md.

## Omvang
- Handmatige UI-checks via Filament admin.
- Backend-acties via queue-jobs (AI reflectie/inzichten).
- Export (CSV/PDF) genereren.
- Bijlagen upload.
- Queue worker async gedrag.

## Testomgeving
- Laravel 12 + PHP 8.4/8.5.
- Database: lokale PostgreSQL (of SQLite fallback voor smoke).
- Queue: database driver met `queue:work`.
- AI-service: stub/placeholder; fouten loggen is ok zolang UI netjes feedback geeft.

## Testscenario's
1. **User onboarding**
   - Inloggen als bestaande admin of nieuwe user registreren.
   - Controleer Filament toegang, navigatie NL labels.

2. **Wapenbeheer**
   - Maak een wapen (type, kaliber, serienummer, opslag, notities).
   - Controleer tabelweergave, filters en relation manager naar sessies.

3. **Sessielog + bijlagen**
   - Maak een sessie met datum/locatie/notities/handmatige reflectie.
   - Voeg minstens één sessiewapen (afstand, schoten, munitie, afwijking, flyers).
   - Upload foto + PDF als bijlage.
   - Bekijk detailpagina: tabbladen, bijlagen zichtbaar en downloadbaar.

4. **AI-acties**
   - Vanuit sessie: actie "Genereer AI-reflectie" → job in queue, status zichtbaar.
   - Vanuit wapen: actie "Genereer AI-inzichten" → job in queue, status zichtbaar.
   - Controleer foutafhandeling bij mislukte AI-call (melding, status failed zonder crash).

5. **AI-coach page**
   - Free-form vraag met optioneel wapen + periode; check antwoord + disclaimer.
   - Historie van vragen zichtbaar.

6. **Export**
   - Export page: selecteer periode + meerdere wapens, genereer CSV + PDF.
   - Valideer content/kolommen + disclaimer.

7. **Queue worker**
   - Start queue worker (database driver) en bevestig dat jobs async afronden zonder exceptions.
   - Inspecteer `failed_jobs` blijft leeg.

## Rapportage
- Noteer per scenario: status (OK/minor/blocked) + observaties/bugs.
- Verzamel relevante logs/stacktraces of terminal output voor regressies.

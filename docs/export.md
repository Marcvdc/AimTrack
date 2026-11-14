# Export

AimTrack kan sessies exporteren naar CSV of PDF voor de eigen verenigingsadministratie. De export is een hulpmiddel; de gebruiker blijft zelf verantwoordelijk voor de actuele eisen.

## Flow
1. Gebruiker opent Filament **Export**-pagina.
2. Formulier valideert periode (van/tot), optionele wapenselectie en formaat (CSV of PDF).
3. `App\Services\Export\SessionExportService` haalt sessies + `session_weapon_entries` van de ingelogde gebruiker op.
4. Service bouwt records per sessie × wapen (datum, baan, locatie, wapen, kaliber, afstand, schoten, munitietype, groepering/score, notities).
5. Service streamt CSV of rendert een Blade-PDF met overzicht + NL-disclaimer.

## CSV
- Kolommen: `datum, baan, locatie, wapen, kaliber, afstand_m, aantal_schoten, munitie, score/groepering, notities`.
- Eén regel per `SessionWeaponEntry`.
- UTF-8 + komma-gescheiden (RFC4180). Decimalen met punt.

## PDF
- Eenvoudige Blade-view met periodekop, totalen per wapen/kaliber en tabel met sessies.
- Disclaimer onderaan: "Let op: dit is een eigen trainingsoverzicht uit AimTrack. Het is niet afgetekend en niet gevalideerd; controleer zelf of het voldoet aan wat je vereniging of een instantie van je vraagt." (NL).
- Render via lichte PDF-lib (bijv. Dompdf). Houd styles minimalistisch zodat printbaar is.

## Validatie & beveiliging
- Queries filteren altijd op `user_id` van de ingelogde gebruiker.
- Periode is verplicht; wapenselectie is optioneel maar beperkt tot eigen wapens.
- Downloads zijn transient; geen lange-termijn opslag tenzij gebruiker dat zelf doet.

## Uitbreidbaarheid
- Nieuwe exportprofielen kunnen als extra service + view worden toegevoegd.
- Eventueel queue-based exports voor grote datasets (job + notificatie met downloadlink) kunnen later worden toegevoegd zonder de huidige interface te breken.

# Export

Handleiding voor CSV/PDF-export.

## Export uitvoeren
1. Open **Export** in het Filament-menu.
2. Kies een **periode** (verplicht) en selecteer optioneel specifieke wapens.
3. Kies een formaat: **CSV** of **PDF**.
4. Klik **Download**. Het bestand wordt direct gedownload.

## CSV
- Geschikt voor spreadsheets. Bevat één regel per sessie × wapen met datum, baan/locatie, afstand, schoten, munitie en notities.

## PDF
- Printvriendelijke versie met overzicht per periode, totalen per wapen/kaliber en een tabel met sessies.
- Ondersteunde disclaimer wordt automatisch toegevoegd: je moet altijd zelf controleren of de export voldoet aan actuele WM-4-eisen.

## Validatie en veiligheid
- Alleen data van de ingelogde gebruiker wordt geëxporteerd.
- Periode is verplicht om te voorkomen dat per ongeluk alle data wordt gedownload.
- Wapenselectie toont alleen eigen wapens.

## Probleemoplossing
- Lege export? Controleer of er sessies bestaan in de gekozen periode en met de gekozen wapens.
- Foutmelding bij downloaden? Controleer opslagpermissies en logs van de `app` container.

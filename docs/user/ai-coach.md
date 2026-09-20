# AI-coach

Hoe je vragen stelt aan de AI-coach en wat je kunt verwachten.

## Vraag stellen
1. Open **AI-coach** in het Filament-menu.
2. Formulier:
   - **Vraag**: vrije tekst (NL) met je coachvraag.
   - **Wapen** (optioneel): selecteer een wapen om de context te beperken.
   - **Periode** (optioneel): kies van/tot om recente sessies te gebruiken.
3. Klik **Stel vraag aan AI-coach**. Het antwoord verschijnt zodra de service klaar is.

## Output
- Antwoord is kort en puntsgewijs met focus op concrete verbeteracties.
- Context komt uit je eigen sessies en wapens; er wordt niets van buitenaf bijgehaald.
- Een disclaimer herinnert je eraan dat AI geen vervanging is voor instructeurs of veiligheidsregels.

## Fouten of timeouts
- Controleer of de AI-providerkey in `.env` staat en dat de queue (indien gebruikt) draait.
- Bij mislukking verschijnt een foutmelding; probeer het opnieuw of verkort de vraag/periode.

## Privacy & veiligheid
- Binnen AimTrack blijft je data bij je eigen account; queries filteren op `user_id`.
- De AI-coach zelf stuurt wel gegevens naar buiten: je vraag gaat samen met de
  sessiecontext (datum, baan, locatie, notities, schotstatistiek, je handmatige
  reflectie) en de wapengegevens (naam, type, kaliber, serienummer, opslaglocatie)
  naar Anthropic (api.anthropic.com) om het antwoord te genereren. Zie
  [Waar je gegevens staan](README.md#waar-je-gegevens-staan).
- Dat loopt op jouw Claude-key of op de gedeelde key van je vereniging; onder dat
  account wordt de vraag verwerkt.
- Zonder API-key doet AimTrack geen enkele call. Een beheerder kan de AI-functie voor
  de hele instance uitzetten met `FEATURE_AIMTRACK_AI`.
- Deel geen gevoelige of illegale vragen; de AI volgt guardrails maar gebruiker blijft verantwoordelijk.

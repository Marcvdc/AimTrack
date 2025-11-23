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
- Context komt uit je eigen sessies/wapens; geen externe data.
- Een disclaimer herinnert je eraan dat AI geen vervanging is voor instructeurs of veiligheidsregels.

## Fouten of timeouts
- Controleer of de AI-providerkey in `.env` staat en dat de queue (indien gebruikt) draait.
- Bij mislukking verschijnt een foutmelding; probeer het opnieuw of verkort de vraag/periode.

## Privacy & veiligheid
- Je data blijft binnen je eigen account; queries filteren op `user_id`.
- Deel geen gevoelige of illegale vragen; de AI volgt guardrails maar gebruiker blijft verantwoordelijk.

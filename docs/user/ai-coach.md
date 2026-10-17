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
- Controleer of er een Claude-key is ingesteld bij AI-instellingen (die van jou of die van je vereniging) en dat de queue (indien gebruikt) draait.
- Bij mislukking verschijnt een foutmelding; probeer het opnieuw of verkort de vraag/periode.

## Privacy & veiligheid
- Binnen AimTrack blijft je data bij je eigen account; queries filteren op `user_id`.
- De AI-coach stuurt wel gegevens naar buiten, zowel bij een reflectie of wapeninzicht
  als in de chat: je vraag gaat naar Anthropic (standaard `api.anthropic.com`). De chat
  haalt zelf op wat hij nodig heeft, dus wat er meegaat hangt af van je vraag. Het kan
  gaan om:
  - **Sessies**: datum, baan en locatie, je ruwe sessienotities, je handmatige
    reflectie, en scores en treffpunten van je schoten, als statistiek.
  - **Per wapen in een sessie**: afstand, aantal patronen, munitiesoort, afwijking en
    je omschrijving van de groepering.
  - **Wapens**: naam, type, kaliber, serienummer, opslaglocatie, of het wapen actief
    of uit gebruik is, en je vrije wapennotities.
  - **Eerdere AI-uitkomsten**: eerdere AI-reflecties op je sessies en eerdere
    AI-inzichten per wapen.
  - **Het chatgesprek**: je vragen en de eerdere berichten uit hetzelfde gesprek, en
    wat de coach over je heeft onthouden (herinneringen).

  Zie ook [Waar je gegevens staan](README.md#waar-je-gegevens-staan).
- Dat loopt op jouw Claude-key of op de gedeelde key van je vereniging; onder dat
  account wordt de vraag verwerkt.
- Zonder API-key doet AimTrack geen enkele AI-call. Een beheerder zet de AI-functie uit
  met `FEATURE_AIMTRACK_AI=false`; wie de AI al gebruikte houdt hem tot de beheerder
  `php artisan pennant:purge aimtrack-ai` draait.
- Deel geen gevoelige of illegale vragen; de AI volgt guardrails maar gebruiker blijft verantwoordelijk.

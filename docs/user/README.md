# AimTrack — Gebruikersdocumentatie

Welkom bij de gebruikersdocumentatie van AimTrack. Hier vind je korte handleidingen
om snel op weg te zijn met je schietsessies, wapens, trends en de AI-coach.

## Handleidingen

- [Snel aan de slag](quickstart.md) — je eerste sessie binnen een paar minuten.
- [Sessies](sessions.md) — sessies aanmaken, schoten loggen en statistieken bekijken.
- [Wapens](weapons.md) — wapens beheren en koppelen aan sessies.
- [AI-coach](ai-coach.md) — hoe de AI-reflectie je groepering en patronen duidt.
- [Exporteren](export.md) — je sessies exporteren naar CSV of PDF.

## Contact & support

Vragen, feedback of een bug gevonden? We horen het graag.

- **E-mail:** [support@aimtrack.nl](mailto:support@aimtrack.nl)
- **Contactformulier:** <https://aimtrack.nl/contact>
- **Issues & broncode:** <https://github.com/Marcvdc/AimTrack>

## Waar je gegevens staan

AimTrack is open-source onder de MIT-licentie en self-hosted: de app en de database
draaien op je eigen server, en je logboek blijft daar staan.

Op één punt gaat er wel data naar buiten, en dat is de AI-coach. Vraag je een
reflectie of een wapeninzicht aan, dan stuurt AimTrack je vraag naar Anthropic
(api.anthropic.com) om het antwoord te laten genereren. Mee gaan:

- **Sessiecontext**: datum, baan en locatie, je ruwe notities, de schotstatistiek
  (series, scores, groepering) en je handmatige reflectie.
- **Wapengegevens**: naam, type, kaliber, **serienummer** en **opslaglocatie**, plus
  per sessie de afstand, het aantal patronen, de munitiesoort en de groepering.

Al het overige, dus je account, je volledige logboek en je exports, blijft op je eigen
server.

Die verwerking loopt op de Claude-key die is ingesteld: die van jou, of de gedeelde key
van je vereniging. Onder dat account wordt de vraag dus verwerkt.

Je kunt het uitzetten. Zonder API-key doet AimTrack geen enkele call naar buiten; je
krijgt dan alleen de melding dat de AI-configuratie ontbreekt. Een beheerder kan de
AI-functie voor de hele instance uitzetten met de omgevingsvariabele
`FEATURE_AIMTRACK_AI`. Zonder AI-coach verlaat er niets je eigen server.

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

### De AI-coach

Van zichzelf stuurt AimTrack alleen gegevens naar buiten voor de AI-coach. Dat gebeurt
op twee manieren: als je een reflectie of een wapeninzicht aanvraagt, en als je de chat
op de coachpagina gebruikt. In beide gevallen gaat je vraag naar Anthropic (standaard
`api.anthropic.com`; een beheerder kan dat adres wijzigen met `ANTHROPIC_BASE_URL` en
`ANTHROPIC_URL`). De chat haalt zelf op wat hij nodig heeft, dus welke gegevens
meegaan hangt af van je vraag. Het kan gaan om:

- **Sessies**: datum, baan en locatie, je ruwe sessienotities, je handmatige
  reflectie, en scores en treffpunten van je schoten, als statistiek.
- **Per wapen in een sessie**: afstand, aantal patronen, munitiesoort, afwijking en
  je omschrijving van de groepering.
- **Wapens**: naam, type, kaliber, **serienummer**, **opslaglocatie**, of het wapen
  actief of uit gebruik is, en je vrije wapennotities.
- **Eerdere AI-uitkomsten**: eerdere AI-reflecties op je sessies en eerdere
  AI-inzichten per wapen.
- **Het chatgesprek**: je vragen en de eerdere berichten uit hetzelfde gesprek, en
  wat de coach over je heeft onthouden (herinneringen).

Wat niet in die lijst staat, zoals je account, je exports en de wapenvelden voor
korrel, vizier, trekkergewicht en grip, stuurt de AI-coach niet mee.

Die verwerking loopt op de Claude-key die is ingesteld: die van jou, of de gedeelde key
van je vereniging. Onder dat account wordt de vraag dus verwerkt.

Je kunt het uitzetten. Zonder API-key doet AimTrack geen enkele AI-call; je krijgt dan
alleen de melding dat de AI-configuratie ontbreekt, en de chat blijft dicht. Een
beheerder zet de AI-functie uit met `FEATURE_AIMTRACK_AI=false`. Let op: AimTrack
onthoudt per gebruiker of de AI aan stond. Voor nieuwe gebruikers geldt het uitzetten
direct, maar wie de AI al gebruikte houdt hem tot de beheerder
`php artisan pennant:purge aimtrack-ai` draait.

### Wat een beheerder verder kan inrichten

Buiten de AI-coach gaat er alleen iets naar buiten als de beheerder van je installatie
dat inricht. De repository kent drie van zulke routes:

- **Mail** via een SMTP-server (`MAIL_MAILER`), voor onder meer het contactformulier en
  meldingen. Storingsmeldingen van de AI-coach gaan standaard naar
  support@aimtrack.nl (`AI_ALERT_EMAIL`).
- **Foutrapportage** naar Sentry, zodra `SENTRY_LARAVEL_DSN` is gezet.
- **Offsite backup** van de database via `rclone`, zodra `RCLONE_REMOTE` is gezet (zie
  [BACKUPS.md](../BACKUPS.md)).

Of die aan staan, weet de beheerder van je installatie.

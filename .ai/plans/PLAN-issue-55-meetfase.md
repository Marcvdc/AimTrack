---
status: IN_PROGRESS
created: 2026-09-20
approved: 2026-09-20
author: Claude (Opus 5)
jira: GH#55
worktree: Shot-bord-markers (Marcvdc/Shot-bord-markers)
basis: e995af5 (main)
---

# PLAN: Issue #55 · Meetfase, foto naar schoten

## Status: IN_PROGRESS

Bouwfase 1 tot en met 3 staan op de branch en zijn op 2026-09-24 geverifieerd met
een testsuite die voor het eerst daadwerkelijk in de testomgeving draaide. De
grondwaarheid (B2) komt niet meer uit een handmatige labelronde maar uit de
correctie-UI, zodra er genoeg bevestigde beurten zijn.

### Verificatie (gedraaid in de aimtrack-dev container)

- `php -l` schoon op alle 25 nieuwe bestanden.
- Pint schoon op alle 25 bestanden.
- 55 nieuwe tests groen (130 assertions).
- Volledige suite: 527 tests groen, 1411 assertions, nul fouten. Geen regressies.
- Commando end-to-end gerookt op een synthetische roos met een ongeldige sleutel:
  manifest inlezen, foto voorbewerken, API-call, foutafhandeling, rapportage en
  het wegschrijven van JSON en Markdown werken allemaal. Alleen het echte
  modelantwoord is nog niet beproefd, want daar zijn de foto's en een sleutel voor nodig.

### Twee fouten die de verificatie eruit haalde

1. `$progress?->(...)` bestaat niet in PHP; `?->` werkt alleen voor methodes en
   properties, niet voor het aanroepen van een nullable closure.
2. Integer-deling in `DetectionMetrics` gaf `int` waar het contract `float` belooft
   (`0/3` is `0`, niet `0.0`). Nu expliciet gecast.

### B1 is opgelost, met twee bevindingen voor het product

De foto's staan er: 65 stuks, waarvan 64 HEIC en 1 JPEG. Omgezet naar JPEG met
`heif-convert -q 95` in `.ai/meetset/fotos-jpg/`; de originelen blijven staan.
Alle 65 zijn door `TargetPhotoPreparer` te lezen en komen uit op 1125x1500,
tussen 229 en 364 KB. Controle vooraf: 64 bronbestanden staan op orientatie
TopLeft (de camera heeft de rotatie al in de pixels gebakken) en de omgezette
JPEG draagt geen Orientation-tag, dus er wordt niet dubbel geroteerd. Het ene
bestand met orientatie 6 loopt door het reguliere exif-pad.

Twee dingen die hieruit komen en die het PRODUCT raken, niet alleen de meting:

1. **HEIC wordt nergens ondersteund.** Een iPhone levert standaard HEIC, en noch
   GD in de dev-image (geen imagick, geen WebP/AVIF) noch de Anthropic API accepteert
   dat formaat. Een upload vanaf een telefoon strandt dus meteen. Er moet een
   conversiestap komen: libheif in de image, of omzetten in de browser voor de upload.
2. **Het geheugen is te krap.** GD pakt een 12MP-foto uit tot ruim 48 MB aan bitmap
   en houdt tijdens het verkleinen origineel en kopie tegelijk vast. Gemeten piek
   over de meetset is 139 MB, waarvan 41 MB Laravel-bootstrap. De container staat
   standaard op 128 MB. Voor de harness is dat opgelost via
   `config('vision.cli_memory_limit')`, maar voor de upload-route in de app moet dit
   apart geregeld worden.

### B3 is deels opgelost

Deze worktree had geen `.env`, waardoor elke test (ook de bestaande) een warning
gaf over `file_get_contents(.env)`. Er staat nu een `.env` op basis van
`.env.example`, met een gegenereerde `APP_KEY`. Die staat in `.gitignore` en reist
dus niet mee. De `ANTHROPIC_API_KEY` erin is nog leeg.

## Aanleiding

Issue #55 loopt sinds 18 juli 2026 vast, niet op de detectie maar op de bezorgroute.
PR #78 (104 bestanden, 10.350 regels) staat CONFLICTING en 48 commits achter op main.
PR #114 stapelt daar bovenop. Op main staat nul van de functionaliteit.

Vastgesteld tijdens onderzoek, met bewijs:

1. Het werkende detectiepad is `detect_holes_direct` en dat gebruikt van OpenCV
   uitsluitend `imencode` en `resize`. Geen enkel CV-algoritme.
2. De Python-service bestaat dus feitelijk alleen voor de klassieke kalibratie,
   en juist die is volgens de validatie van 18 juli de zwakke schakel
   (ongeveer 30 mm RMS op geplakte rozen). PR #114 haalt hem alsnog uit het pad.
3. Laravel praat in `ShooterCoach` al rechtstreeks met de Anthropic Messages API
   via `Http::`, met de BYO-key-resolutie in `AiKeyResolver`.
4. GD staat met jpeg en exif in de Dockerfile, dus downschalen en base64 kan PHP zelf.

Conclusie die getoetst moet worden: de Python-service is overbodig voor het pad
dat werkt. Deze fase meet dat in plaats van het aan te nemen.

## Doel van deze fase

Eén ding: cijfers op tafel krijgen waarmee de architectuurkeuze te maken is.
Er wordt in deze fase geen productiefunctionaliteit opgeleverd en geen regel
bestaande code gewijzigd. Alleen nieuwe bestanden.

## Niet in scope

- De upload-UI, de correctie-UI en de `needs_review`-badge.
- Het mergen, rebasen of sluiten van PR #78 en PR #114.
- Het deployen van de Python-service.
- Elke wijziging aan bestaande klassen.

## Benodigde input (blokkerend, alleen Marc kan dit leveren)

- B1: de 49 foto's. Staan nergens meer op deze machine, ook niet in een andere
  worktree. Alleen `manifest.provisional.json` met 6 namen is bewaard gebleven.
- B2: grondwaarheid per foto: discipline, echt aantal schoten, echte ringwaarden.
  Zonder dit is alleen consistentie meetbaar, niet nauwkeurigheid, en dat is
  precies de meting die tot nu toe telkens is overgeslagen.
- B3: een `ANTHROPIC_API_KEY` in deze worktree. Er is hier geen `.env`.

## Op te leveren

### 1. `app/Services/Vision/TargetPhotoAnalyzer.php`
Pure PHP-poort van het vision-direct pad.
- Exif-rotatie rechtzetten, downschalen naar 1500 px langste zijde via GD.
- Eén Anthropic Messages-call met image-block en JSON-schema structured output.
- Schema en systeemprompt 1 op 1 geport uit `_DIRECT_SHOT_SCHEMA` en
  `_direct_system_prompt`, met één toevoeging: een `rejected`-array, zodat de
  overlay laat zien wat het model bewust heeft weggegooid (plakker, ringcijfer,
  scheur). Dat is het verschil tussen "hij mist een schot" en "hij wijst het af".
- Key via `AiKeyResolver`, met omgevingssleutel als terugval voor de harness.
- Geen enkele afhankelijkheid van de Python-service.

### 2. `app/Services/Vision/DetectionEvalHarness.php` plus artisan-command
`php artisan aimtrack:eval-detectie {manifest} {--runs=3} {--out=}`
- Draait elke foto N keer onafhankelijk.
- Metrics per foto: `count_delta` tegen het echte aantal, ring-MAE tegen de
  grondwaarheid, en spreiding van de positie over de runs uitgedrukt in
  ring1-stralen (de maat waarin de julimeting ook rapporteerde).
- Aggregaat per discipline en over de hele set.
- Rapport als JSON plus een leesbaar Markdown-overzicht.

### 3. Overlay-generator
Rode cirkel per gedetecteerd schot, cyaan per afgewezen kandidaat, nummer erbij.
Zelfde visuele taal als de julimeting, zodat de uitkomsten vergelijkbaar zijn.
Puur GD, geen extra dependency.

### 4. Tests (Pest)
- Analyzer met gemockte `Http::fake()`: schema-validatie, clamping van waarden
  buiten bereik, exif-rotatie, downschaal-gedrag, foutpaden (geen key,
  ongeldige JSON, API-fout).
- Harness-metrics op vaste kunstmatige invoer: `count_delta`, ring-MAE,
  spreiding en aggregatie.
- Geen echte API-call in de suite.

## Acceptatiecriteria

1. De harness draait op een manifest en levert JSON plus Markdown, zonder dat er
   een Python-service draait.
2. De analyzer doet precies één netwerkcall per foto per run en werkt met een
   key uit `AiKeyResolver` of uit de omgeving.
3. Overlays tonen gedetecteerde en afgewezen punten los van elkaar.
4. De rapportage geeft per discipline en over de set: aantal-afwijking,
   ring-MAE en positiespreiding.
5. Pest groen, Pint schoon, geen bestaande klasse gewijzigd.
6. Er draait geen echte API-call in de testsuite.

## Beslissing die hierna genomen wordt

Met de cijfers ernaast kiezen we tussen de Laravel-herbouw en het redden van
PR #78. De meting beslist, niet het onderbuikgevoel.

## Risico dat nu al benoemd moet worden

Zonder B2 meet de harness alleen consistentie tussen runs, niet of de detectie
klopt. Consistentie was in juli al aangetoond. De openstaande klacht is
nauwkeurigheid, en die is per definitie niet meetbaar zonder grondwaarheid.

## Vervolgfase: correctie-UI als labelgereedschap

Goedgekeurd op 2026-09-20, uit te voeren na de meting.

### Waarom dit een fase op zich is

Het labelen van foto's is handwerk dat je een keer doet en daarna kwijt bent. Dat
is precies wat er gebeurd is: de 49 foto's en hun labels bestaan niet meer, en de
enige die overbleef is een provisioneel manifest dat het model zelf heeft ingevuld.

De correctie-UI die het product sowieso nodig heeft, is tegelijk het gereedschap dat
dat probleem structureel wegneemt. Elke correctie die een schutter in de app maakt,
is een menselijke grondwaarheid op een echte foto, en die blijft in de database staan.
De meetset groeit dan mee met het gebruik in plaats van te verdampen.

### Op te leveren

1. **Versleepbare markers op het schotbord.** Slepen verplaatst een schot
   (`source` wordt `photo_corrected`), klikken verwijdert het, klikken op een lege
   plek voegt er een toe. Dit raakt ook issue #144, waar het bord taps dicht op
   elkaar negeert en ongevraagd de verwijdermodal opent.
2. **Een bevestigknop** die de controlevlag van de beurt wegzet.
3. **Correcties bewaren als label.** Per beurt vastleggen wat het model zei en wat
   de mens ervan maakte, gekoppeld aan de foto, zodat de eval-set zich vult.
4. **Exportpad naar de harness**, zodat `aimtrack:eval-detectie` op de verzamelde
   correcties kan draaien zonder handmatig manifest.

### Volgorde

Deze fase komt na de meting, want de meting bepaalt of de detectie in Laravel of in
de Python-service komt te staan, en dat bepaalt waar de correctie tegenaan praat.

## Bouwfase stap 1: HEIC en geheugen (2026-09-21, goedgekeurd)

Besluit vooraf: geen ronde 2 draaien. De architectuurvraag (Laravel-herbouw versus
PR #78 redden) is beantwoord door wat er inmiddels draait, en 55 dollar aan extra
meten verandert die uitkomst niet. De grondwaarheid komt uit de correctie-UI zodra
die er is, in plaats van uit een dure batch plus handmatig labelwerk.

### Wat er gebouwd is

Het decoderen is uit `TargetPhotoPreparer` gehaald en achter een `PhotoDecoder`
gezet, met twee implementaties:

- `ImageMagickPhotoDecoder` (voorkeur): draait ImageMagick in een apart proces.
- `GdPhotoDecoder` (terugval): het oude gedrag, ongewijzigd.

De preparer kiest ImageMagick zodra het beschikbaar is. Beide decoders worden in
de tests langs dezelfde dataset gehaald, zodat hun gedrag naar buiten toe gelijk
blijft; de ImageMagick-cases slaan over in een omgeving zonder die binary.

`imagemagick` en `libheif1` staan nu in de base-stage van de Dockerfile. De
meetset is toegevoegd aan `.dockerignore`: 284 MB foto's hoort niet in de image.

### Waarom dit een ingreep is en geen pleister

Een hogere `memory_limit` was de voor de hand liggende fix geweest, maar dan blijft
het uitpakken binnen PHP gebeuren en schuift de grens alleen op. Door het werk naar
een apart proces te brengen verdwijnt het probleem, en HEIC komt er gratis bij.

### Gemeten resultaat

- 8 echte HEIC-bestanden rechtstreeks verwerkt, zonder voorbewerking: 8 van 8.
- Piekgeheugen PHP 42 MB tegen de standaardlimiet van 128 MB. Voorheen 139 MB.
- Zelfde uitvoer als het GD-pad: 1125x1500, 350 tot 380 KB.
- GD faalt op dezelfde HEIC met een melding die naar ImageMagick wijst.
- 563 tests groen, Pint schoon.

### Afgerond op 2026-09-24

De dev-image is herbouwd en draait. ImageMagick 6.9.11 zit erin met HEIC in de
formatlijst, en vijf echte HEIC-bestanden uit de meetset zijn rechtstreeks door
`TargetPhotoPreparer` gehaald: 1125x1500, piekgeheugen 50 MB tegen de
standaardlimiet van 128 MB. De ImageMagick-cases in `PhotoDecoderTest` werden
daarvoor overgeslagen bij gebrek aan de binary en draaien nu mee.

Staging en productie hebben dezelfde herbouw nog nodig.

## Bouwfase stap 2: foto per beurt naar schoten (2026-09-21)

### Wat er gebouwd is

Database: `sessions.target_type`, `session_shots.source` (manual, photo,
photo_corrected) en de tabel `session_turn_analyses` met één rij per beurt.

`TurnPhotoAnalysisService` vertaalt een analyse naar schoten op het bord, met de
ring1-schaal uit `TargetFrame` erin verwerkt zodat een kaart die bij ring 6 ophoudt
niet twee keer te ver naar buiten belandt. `AnalyzeTurnPhotoJob` draait dat in de
wachtrij, want een vision-call met denkwerk duurde in de metingen 90 seconden.

Op het schotbord: een upload-actie per beurt met een optioneel aantal schoten, en
een melding met een knop "Beurt bevestigen".

### Keuzes die het gedrag bepalen

De foto gaat nooit verloren. Ook bij een mislukte herkenning blijft het pad bewaard
en staat de beurt op te controleren, zodat een schutter handmatig verder kan.

Er wordt nooit aangevuld tot het ingevulde aantal. Te weinig schoten met een
controlevlag is beter dan een plakker die als schot op het bord komt.

Opnieuw analyseren vervangt alleen de schoten met bron `photo`. Handmatig geplaatste
en met de hand gecorrigeerde schoten laat de job staan: die zijn door een mens
neergezet.

Elke beurt uit een foto komt op te controleren. De detectie is goed maar niet
feilloos, en een fout die ongemerkt in de statistiek belandt kost meer dan twee
klikken correctie.

HEIC staat expliciet in de toegestane uploadtypes, want dat is wat een iPhone levert.

### Status

586 tests groen (23 nieuwe rond deze stap), Pint schoon.

### Nog te doen

De correctie-UI met versleepbare markers. Zolang die er niet is, kan een schutter
een verkeerd geplaatst schot alleen verwijderen en handmatig opnieuw zetten.

## Bouwfase stap 3: correctie-UI en meetset-export (2026-09-21)

### Wat er gebouwd is

Markers zijn versleepbaar. `SessionShotService::moveShot()` verplaatst een schot,
herberekent de score, en zet de bron van een fotoschot op `photo_corrected` met de
oorspronkelijke positie en ring in `metadata.corrected_from`.

In de canvas is een sleepdrempel van 6 pixels ingebouwd. Daaronder blijft het een
klik, zodat lang indrukken om te verwijderen blijft werken zoals het deed; daarboven
wordt het slepen en wordt het lang-indrukken afgebroken. Zonder die marge zou elke
trilling van een vinger op een telefoon een schot verplaatsen.

`CorrectionExportService` plus `aimtrack:meetset-exporteren` bouwt een manifest uit
de beurten die een mens heeft bevestigd, direct bruikbaar voor
`aimtrack:eval-detectie`.

### Waarom de export het sluitstuk is

Het labelen is in dit issue twee keer misgegaan: de eerste set van 49 foto's is van
de machine verdwenen, en de labels die overbleven waren door het model zelf ingevuld
en dus waardeloos als meetlat. Nu komt de grondwaarheid uit gewoon gebruik. Alleen
bevestigde beurten tellen mee; een beurt die nog op controleren staat is per
definitie geen grondwaarheid.

Een tweede correctie op hetzelfde schot overschrijft `corrected_from` niet, zodat
het oorspronkelijke modelantwoord als ijkpunt blijft staan.

### Status

603 tests groen (17 nieuwe bij deze stap), Pint schoon op 301 bestanden.

### Wat hierna pas zinvol wordt

Ronde 2 van de meting, zodra er genoeg bevestigde beurten zijn. Dan draait de
harness op menselijke grondwaarheid in plaats van op een gok, en kunnen de
zekerheidsdrempel en de prompt geijkt worden op cijfers.

## Afronding bouwfase (2026-09-24)

### De testsuite bewees minder dan hij leek te bewijzen

Bij het afronden faalde één test: de assertie dat het opgegeven aantal schoten in
`AnalyzeTurnPhotoJob` terechtkomt. De job werd wel gepusht, maar
`expectedShotCount` was null in plaats van 5.

De oorzaak lag niet in deze feature. Laravel leest omgevingswaarden uit
`$_SERVER`, terwijl de `<env>`-regels in `phpunit.xml` alleen `$_ENV` en
`putenv` raken. De dev-container zet die variabelen zelf via `env_file`, dus de
containerwaarde won. Voor `DB_CONNECTION` was dat ooit al ontdekt en opgelost met
een `<server force>`-regel; de overige instellingen hadden die behandeling nooit
gekregen.

Daardoor draaide de suite met `APP_ENV=local`. Dat is stiller dan het klinkt:
Filament's `fillFormDataForTesting()` begint met een controle op
`app()->runningUnitTests()` en keert zonder melding terug als die false is. Elke
waarde die een test via `callAction()` of `fillForm()` aan een formulier meegaf,
werd dus genegeerd. Tests die `null` verwachtten stonden groen om precies de
verkeerde reden, en alleen de test die een echte waarde controleerde viel om.

Vier van de zeven instellingen kwamen niet aan. Naast `APP_ENV` liepen cache en
sessie over schijf, waardoor state tussen testruns bleef staan en een
rate-limittest afhing van de vorige run, gingen jobs naar de database-queue in
plaats van sync, en stond mail op echte SMTP.

Dit is vastgelegd in `phpunit.xml` met een toelichting, zodat de volgende keer
niet opnieuw uitgezocht hoeft te worden waarom een `<env>`-regel geen effect
heeft. Losse commit, want het raakt het hele project en niet dit issue.

### Geheugen van de suite

De volledige run viel halverwege om op een Livewire-render met een uitgeputte
`memory_limit`. Dat is geen lek: de suite heeft rond de 192 MB nodig en de
container staat op 128 MB. Er staat nu een `<ini name="memory_limit">` in
`phpunit.xml`, zodat de grens ook geldt bij `php artisan test` (die geeft een
`-d` op de commandoregel niet door aan het subproces).

### Stand

603 tests groen, 1603 assertions. Pint schoon.

Twee commits op `Marcvdc/Shot-bord-markers`:

- `fix(tests)`: de phpunit-instellingen over de containeromgeving.
- `feat(vision)`: bouwfase 1 tot en met 3, 56 bestanden.

### Wat nog niet is aangetoond

De vision-call is nooit end-to-end met een echte sleutel gedraaid in deze ronde;
alle tests gebruiken `Http::fake()`. De correctie-UI is alleen via
Livewire-tests geverifieerd en niet met een echte muis of vinger in een browser,
terwijl juist de sleepdrempel van 6 pixels om handmatige bediening vraagt.

### Bijvangst

De `.gitignore` in `.ai/meetset/` sloot `uit/` uit terwijl de uitvoermap
`uit-ronde1/` heet. Daardoor stonden 66 overlay-foto's (23 MB) op het punt de
historie in te gaan. De regel staat nu op `uit*/`.

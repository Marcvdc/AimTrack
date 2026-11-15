# Browsertests (Pest browser plugin)

De tests in `tests/Browser/` draaien met `pestphp/pest-plugin-browser` tegen de echte pagina in
Chromium, via Playwright. Ze bestaan omdat sommig gedrag alleen in de browser zit, zoals het
schotenbord: een tik met een vinger plaatst een schot, een rechtsklik met de muis op een marker
opent de verwijdermodal (#144). Een Livewire-test roept alleen de servermethode aan en ziet dat niet.

## Wat erbij hoort

- Composer (require-dev): `pestphp/pest-plugin-browser` (v4, past bij Pest 4). Die eist de
  PHP-extensies `sockets` en `pcntl`.
- npm (devDependencies): `playwright`. De plugin start `node_modules/.bin/playwright run-server`.
- Een Chromium die bij die Playwright-versie hoort, plus de systeembibliotheken daarvoor.
- Gebouwde assets (`npm run build`): de browsertests zetten de `withoutVite()` uit `TestCase`
  terug aan, zodat de pagina met de echte CSS en layout rendert.

De browsertests staan niet in de testsuites van `phpunit.xml`. `php artisan test` en
`vendor/bin/pest` slaan ze dus over; ze draaien alleen met het pad `tests/Browser` erbij. Zo blijft
de gewone suite draaien in de dev-container, waar geen Node of browser in zit.

## Lokaal draaien

De dev-image heeft geen Node en geen browser. Daarvoor is er de Docker-stage `browser-tests`
(dev-image plus Node 24, `pcntl` en Chromium) en de compose-service `browser-tests` in
`docker/compose.dev.yml`, achter het profiel `browser`. Op de host, in de werkmap of worktree:

```bash
npm ci                 # node_modules met playwright, in de gemounte werkmap
npm run build          # public/build voor de Vite-manifest
docker compose --env-file .env -f docker/compose.dev.yml build browser-tests
docker compose --env-file .env -f docker/compose.dev.yml run --rm browser-tests
```

Een enkele test: zet het pad en een filter achter `run --rm browser-tests`, bijvoorbeeld
`php vendor/bin/pest tests/Browser --filter=rechtsklik`.

De service draait als je eigen UID (nooit als root) en heeft geen database nodig: de tests gebruiken
SQLite in het geheugen, net als de gewone suite. De Chromium-versie in de image volgt `playwright`
uit `package-lock.json`. Na een update van `playwright` moet de image dus opnieuw gebouwd worden;
anders meldt de plugin dat Playwright verouderd is.

Bij een mislukte test bewaart de plugin een screenshot in `tests/Browser/Screenshots/` (staat in
`.gitignore`).

## CI

De job `Browsertests (Pest)` in `.github/workflows/ci.yml` installeert de dependencies, bouwt de
assets, installeert Chromium (gecachet per Playwright-versie) en draait `vendor/bin/pest
tests/Browser`. Bij een fout gaan de screenshots mee als artifact `browser-screenshots`.

## Valkuilen

- `Page::waitForFunction()` van de plugin (v4.3.1) stuurt zijn verzoek nooit naar Playwright: de
  aanroep maakt een generator aan die niet doorlopen wordt, dus er wordt niet gewacht. Wacht met
  `$page->script()` en een belofte in de pagina, zoals `waitForMarkers()` in
  `tests/Browser/SessionShotBoardTapTest.php`.
- `visit($url)->on()->mobile()` geeft een `On`-object terug, geen pagina. Elke methode die je daarop
  aanroept maakt een nieuwe pagina aan. Roep eerst een assertion aan (bijvoorbeeld
  `assertNoJavaScriptErrors()`) en werk verder met wat die teruggeeft.
- Een echte tik met touch doe je met `->on()->mobile()` (context met `hasTouch`) en
  `$locator->elementHandle()->tap(['position' => [...]])`. Lang indrukken kan Playwright niet; die
  eventreeks wordt in de test met `dispatchEvent` nagebootst.

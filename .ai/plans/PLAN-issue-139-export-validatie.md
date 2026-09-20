# Plan: validatie, controller en throttle op de sessie-exportroute

**Taak**: GitHub-issue #139 "De export-route valideert niets en geeft HTTP 500 op een foute datum"
**Status**: APPROVED (onbemande orchestrator-run, afbakening vastgelegd in de issue-comment)

**Stack**

```
Repo: aimtrack/aimtrack
Stack: Laravel 12 / PHP 8.4 / Type A (Eloquent) / Filament 5 / Filament-auth / Pest 4 / PostgreSQL (prod) + SQLite (tests)
KJ-packages: geen (eigen GitHub-native project, geen kjsoftware/*)
Boost actief: ja (AGENTS.md + laravel-boost in composer.json). De Boost-MCP kon in deze sessie niet
verbinden (wsl.exe niet gevonden), dus de stackdetectie is uit composer.json en de bestanden zelf gedaan.
```

## Probleem

De downloadroute `routes/web.php:21-33` is een closure die de querystring rechtstreeks aan
`Carbon::parse()` en aan de database geeft. Een foute datum geeft HTTP 500, een niet-numerieke
`weapon_ids` geeft 500 op PostgreSQL, en een ontbrekende `from`/`to` levert stilzwijgend een leeg
bestand op. Er staat geen throttle op, terwijl dit met vijf eager-loaded relaties en een DomPDF-render
de duurste route van de app is.

## Aanpak

Type A (Eloquent), dus de laagindeling is: route -> Controller -> FormRequest -> Service. De closure
verdwijnt uit `routes/web.php` en wordt een invokable controller die uitsluitend gevalideerde waarden
aan de bestaande `SessionExportService` doorgeeft. De validatie komt in een FormRequest met
Nederlandse foutmeldingen (array-gebaseerde regels, de stijl van `app/Livewire/ContactForm.php:32-38`);
er is geen `lang/nl`-map in de repo, dus de teksten staan in `messages()` en `attributes()` van de
FormRequest zelf. `weapon_ids` komt als komma-string binnen, dus die wordt in `prepareForValidation()`
genormaliseerd naar een array en per element gevalideerd als integer die bestaat én van de ingelogde
gebruiker is. Bij een ongeldige aanvraag gaat de gebruiker terug naar de Filament-exportpagina met een
Nederlandse danger-notificatie, geen 500 en geen leeg bestand. De route krijgt `throttle:10,1` achter
`auth`, zodat de teller per gebruiker loopt. `SessionExportService` blijft ongewijzigd: zelfde velden,
zelfde CSV- en PDF-inhoud, zelfde filters.

## Raakt

- Route: `routes/web.php`: closure vervangen door `SessionExportController`, middleware wordt
  `['auth', 'throttle:10,1']`. De ongebruikte `Carbon`- en `SessionExportService`-imports gaan eruit.
- Controller: `app/Http/Controllers/SessionExportController.php` (nieuw): invokable, ontvangt
  `ExportSessionsRequest` + `SessionExportService`, geeft de gevalideerde periode, wapens en het
  formaat door. Geen Model-queries, geen business-logica.
- Request: `app/Http/Requests/ExportSessionsRequest.php` (nieuw): `from`/`to` `required|date` met
  `to` `after_or_equal:from`, `format` `nullable|in:csv,pdf`, `weapon_ids` `nullable|array` en
  `weapon_ids.*` `integer` + `Rule::exists('weapons', 'id')->where('user_id', <ingelogde gebruiker>)`.
  `prepareForValidation()` splitst de komma-string. `failedValidation()` stuurt een Nederlandse
  Filament-notificatie en redirect naar `ExportSessionsPage::getUrl()`. Helpers `periodFrom()`,
  `periodTo()`, `weaponIds()` en `format()` leveren getypeerde waarden voor de controller.
- Docs: `docs/architecture.md`: de regel over de exportflow noemt nu de FormRequest, de controller
  en de throttle.
- Test: `tests/Feature/ExportSessionsValidationTest.php` (nieuw): zie hieronder.

Niet aangeraakt: `app/Services/Export/SessionExportService.php`, `app/Enums/`, `config/app.php`, en de
teksten/labels rond de export (#131, #143 en #145 lopen parallel).

## Tests

Pest 4, feature-tests in `tests/Feature/ExportSessionsValidationTest.php`:

1. Een geldige aanvraag met een eigen `weapon_id` geeft 200 en een CSV-content-type (happy path, en
   bewijst dat de nieuwe validatie de bestaande export niet breekt).
2. `from=geen-datum` geeft 302 naar de exportpagina met een sessiefout op `from`, geen 500.
3. Ontbrekende `from` en `to` geven 302 met sessiefouten op beide velden, geen leeg bestand met 200.
4. `to` vóór `from` geeft een sessiefout op `to`.
5. `format=xml` geeft een sessiefout op `format`.
6. `weapon_ids=abc` geeft een sessiefout op `weapon_ids.0` en bereikt de query nooit (dekt het
   PostgreSQL-only 500 uit het issue af op SQLite).
7. Een `weapon_id` van een andere gebruiker geeft een sessiefout op `weapon_ids.0`.
8. De elfde aanroep binnen een minuut geeft 429 (throttle).
9. De foutmeldingen zijn Nederlands (assert op de tekst van de eerste melding).

Draaien met `php artisan test --compact tests/Feature/ExportSessionsValidationTest.php` plus de
bestaande `SessionExportTest.php` en `ExportSessionsHttpsTest.php` als regressiecheck, en
`vendor/bin/pint --dirty`.

## Open vragen

Geen.

## Volgende stap

`/ontwikkeling:ontwikkelen-bouwen`.

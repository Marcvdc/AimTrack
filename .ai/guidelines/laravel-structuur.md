# Applicatiestructuur: Laravel 12, geen Laravel 10-kernels

AimTrack draait op de moderne Laravel-structuur. `bootstrap/app.php` is het enige punt waar
providers, routing, middleware en exception-handling bedraad worden, via `Application::configure()`.

## Wat waar staat

- **Middleware-klassen**: `app/Http/Middleware/`. **Registratie**: de `withMiddleware()`-callback in
  `bootstrap/app.php`. Er is geen `app/Http/Kernel.php`.
- **Let op**: in `withMiddleware()` is de configuratie nog niet geladen, dus `config()` werkt daar
  niet. Middleware die configuratie nodig heeft leest die zelf op requestmoment; zie
  `config/trustedproxy.php` en `Illuminate\Http\Middleware\TrustProxies`.
- **Service providers**: `app/Providers/`, geregistreerd in `withProviders()` in `bootstrap/app.php`.
  Dit project heeft geen `bootstrap/providers.php`.
- **Routes**: `withRouting()` is de enige plek die routebestanden laadt: `routes/web.php`,
  `routes/api.php` (prefix `/api`, groep `api`) en `routes/console.php`. Er is geen
  `app/Providers/RouteServiceProvider.php` meer; die laadde `routes/web.php` een tweede keer (#160).
- **Console-commando's en schedule**: `routes/console.php`. Er is geen `app/Console/Kernel.php`.
- **Exception-handling**: de `withExceptions()`-callback. `app/Exceptions/Handler.php` staat er nog,
  maar de container bindt `Illuminate\Foundation\Exceptions\Handler`, dus die klasse doet niets.

## Niet opnieuw aanmaken

`app/Http/Kernel.php` en `app/Console/Kernel.php` werden na de Laravel 12-upgrade nooit meer geladen:
`public/index.php` draait `$app->handleRequest()` en `artisan` draait `$app->handleCommand()`, beide
via `Application::configure()`. De registraties in die twee bestanden hadden dus geen effect, met een
ongevalideerde Host-header en ongeconfigureerde proxies tot gevolg. Ze zijn verwijderd, samen met de
negen nooit-geregistreerde middleware-subklassen die alleen door die Kernel genoemd werden.

`tests/Feature/Http/MiddlewareRegistrationTest.php` faalt zodra een van die bestanden terugkomt.
Moet er middleware bij, dan gaat dat via `bootstrap/app.php`.

Hetzelfde geldt voor `app/Providers/RouteServiceProvider.php`: naast `withRouting()` registreerde
die alle webroutes nog een keer. `tests/Feature/Http/RouteRegistrationTest.php` faalt zodra er weer
een tweede route-provider bij komt. Een nieuw routebestand hoort in `withRouting()`.

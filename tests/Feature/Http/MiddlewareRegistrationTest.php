<?php

use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Middleware\TrustProxies;

/*
 * Regressietests bij GHSA-r7rw-cf8g-r234.
 *
 * app/Http/Kernel.php werd in Laravel 12 nooit geladen, waardoor TrustHosts
 * helemaal niet in de stack zat en TrustProxies ongeconfigureerd bleef. Deze
 * tests borgen dat de registratie in bootstrap/app.php blijft staan.
 */

test('TrustHosts staat in de globale middleware-stack', function () {
    $global = app(HttpKernelContract::class)->getGlobalMiddleware();

    expect($global)->toContain(TrustHosts::class);
});

test('TrustProxies staat in de globale middleware-stack', function () {
    $global = app(HttpKernelContract::class)->getGlobalMiddleware();

    expect($global)->toContain(TrustProxies::class);
});

test('TrustHosts vertrouwt de applicatie-host en subdomeinen daarvan', function () {
    config()->set('app.url', 'https://aimtrack.nl');

    $hosts = app(TrustHosts::class)->hosts();

    expect($hosts)->toContain('^(.+\.)?aimtrack\.nl$');
});

test('TrustHosts vertrouwt een vreemde host niet', function () {
    config()->set('app.url', 'https://aimtrack.nl');

    $patterns = app(TrustHosts::class)->hosts();

    $matchesEvil = collect($patterns)
        ->filter()
        ->contains(fn (string $pattern): bool => preg_match('#'.$pattern.'#i', 'evil.example.com') === 1);

    expect($matchesEvil)->toBeFalse();
});

/*
 * TrustProxies leest config('trustedproxy.proxies') zelf uit. Die sleutel moet
 * de env-waarde doorgeven en mag geen eigen default hebben: met '*' vertrouwt
 * Laravel elke aanroeper en kan iemand die de origin rechtstreeks bereikt zijn
 * eigen X-Forwarded-For zetten (issue #123). Het configbestand wordt per geval
 * opnieuw ingelezen, zodat de test de koppeling toetst en niet de waarde die bij
 * het opstarten toevallig in de omgeving stond.
 */
function laadTrustedProxyConfigMet(?string $waarde): mixed
{
    $vorige = [getenv('TRUSTED_PROXIES'), $_ENV['TRUSTED_PROXIES'] ?? null, $_SERVER['TRUSTED_PROXIES'] ?? null];

    if ($waarde === null) {
        putenv('TRUSTED_PROXIES');
        unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
    } else {
        putenv("TRUSTED_PROXIES={$waarde}");
        $_ENV['TRUSTED_PROXIES'] = $_SERVER['TRUSTED_PROXIES'] = $waarde;
    }

    try {
        return (require config_path('trustedproxy.php'))['proxies'];
    } finally {
        [$env, $envArray, $server] = $vorige;
        $env === false ? putenv('TRUSTED_PROXIES') : putenv("TRUSTED_PROXIES={$env}");
        if ($envArray === null) {
            unset($_ENV['TRUSTED_PROXIES']);
        } else {
            $_ENV['TRUSTED_PROXIES'] = $envArray;
        }
        if ($server === null) {
            unset($_SERVER['TRUSTED_PROXIES']);
        } else {
            $_SERVER['TRUSTED_PROXIES'] = $server;
        }
    }
}

test('de vertrouwde proxies komen uit de env-waarde TRUSTED_PROXIES', function (string $waarde) {
    expect(laadTrustedProxyConfigMet($waarde))->toBe($waarde);
})->with([
    'enkel bereik' => '10.9.8.0/24',
    'meerdere bereiken' => '127.0.0.1/32,172.16.0.0/12',
]);

test('zonder TRUSTED_PROXIES wordt geen enkele proxy vertrouwd', function () {
    expect(laadTrustedProxyConfigMet(null))->toBeNull();
});

test('.env.example vertrouwt niet elke proxy', function () {
    $regel = collect(file(base_path('.env.example'), FILE_IGNORE_NEW_LINES))
        ->first(fn (string $regel): bool => str_starts_with($regel, 'TRUSTED_PROXIES='));

    expect($regel)->not->toBeNull()
        ->and($regel)->not->toContain('*');
});

test('InjectUserAnthropicKey hangt in de web-groep', function () {
    $groups = app(HttpKernelContract::class)->getMiddlewareGroups();

    expect($groups['web'] ?? [])->toContain(App\Http\Middleware\InjectUserAnthropicKey::class);
});

test('de dode Laravel 10-kernels bestaan niet meer', function () {
    expect(file_exists(base_path('app/Http/Kernel.php')))->toBeFalse()
        ->and(file_exists(base_path('app/Console/Kernel.php')))->toBeFalse();
});

/*
 * De app-subklassen in app/Http/Middleware/ werden alleen door de dode
 * app/Http/Kernel.php genoemd en zijn met die Kernel meeverwijderd. Ze mogen
 * niet terugkeren: een bestand dat eruitziet als geregistreerde middleware maar
 * het niet is, is precies de val die dit issue veroorzaakte.
 */
test('de nooit-geregistreerde Laravel 10-middleware bestaan niet meer', function (string $class) {
    expect(file_exists(base_path("app/Http/Middleware/{$class}.php")))->toBeFalse();
})->with([
    'Authenticate',
    'EncryptCookies',
    'PreventRequestsDuringMaintenance',
    'RedirectIfAuthenticated',
    'TrimStrings',
    'TrustHosts',
    'TrustProxies',
    'ValidateSignature',
    'VerifyCsrfToken',
]);

test('InjectUserAnthropicKey blijft wel bestaan want die is echt geregistreerd', function () {
    expect(file_exists(base_path('app/Http/Middleware/InjectUserAnthropicKey.php')))->toBeTrue();
});

/*
 * Regressiegrens bij het verwijderen van App\Http\Middleware\Authenticate.
 * Die klasse stuurde gasten naar route('login'), maar werd nooit geregistreerd;
 * de 'auth'-alias wees al naar de framework-versie. Laravel 12's Authenticate
 * geeft zelf null terug en laat de redirect aan de exception handler, die
 * terugvalt op route('login'). Deze test legt vast dat dat pad blijft werken.
 */
test('een gast op een auth-route wordt naar login gestuurd', function () {
    $this->get(route('exports.sessions.download'))
        ->assertRedirect(route('login'));
});

test('een gast op een auth-route krijgt json-401 in plaats van een redirect', function () {
    $this->getJson(route('exports.sessions.download'))
        ->assertUnauthorized();
});

/*
 * App\Http\Middleware\TrimStrings droeg als enige van de verwijderde klassen
 * een echte aanpassing: een $except voor de wachtwoordvelden, zodat spaties in
 * een wachtwoord niet worden weggeknipt. Die aanpassing is nooit toegepast (de
 * Kernel werd niet geladen), maar Laravel 12 sluit dezelfde drie velden zelf al
 * uit. Deze test legt vast dat we daarop mogen leunen.
 */
test('het framework knipt de wachtwoordvelden zelf al niet', function () {
    $except = (new ReflectionClass(Illuminate\Foundation\Http\Middleware\TrimStrings::class))
        ->getDefaultProperties()['except'];

    expect($except)->toContain('current_password')
        ->toContain('password')
        ->toContain('password_confirmation');
});

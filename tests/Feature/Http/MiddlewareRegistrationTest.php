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

test('de vertrouwde proxies komen uit config in plaats van uit een dode middleware', function () {
    expect(config()->has('trustedproxy.proxies'))->toBeTrue();
});

test('InjectUserAnthropicKey hangt in de web-groep', function () {
    $groups = app(HttpKernelContract::class)->getMiddlewareGroups();

    expect($groups['web'] ?? [])->toContain(App\Http\Middleware\InjectUserAnthropicKey::class);
});

test('de dode Laravel 10-kernels bestaan niet meer', function () {
    expect(file_exists(base_path('app/Http/Kernel.php')))->toBeFalse()
        ->and(file_exists(base_path('app/Console/Kernel.php')))->toBeFalse();
});

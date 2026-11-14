<?php

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as FrameworkRouteServiceProvider;
use Illuminate\Support\Facades\Route;

/*
 * Issue #160: routes/web.php werd twee keer geregistreerd, door withRouting() in
 * bootstrap/app.php en door het Laravel 10-restant App\Providers\RouteServiceProvider.
 * De RouteCollection overschrijft een tweede registratie op dezelfde methode en
 * URI stil, dus de dubbeling is in route:list niet te zien. Daarom toetst de
 * eerste test het aantal route-providers: withRouting() registreert er zelf
 * precies één.
 */

test('er laadt precies één route-provider de routebestanden', function () {
    $routeProviders = app()->getProviders(FrameworkRouteServiceProvider::class);

    expect($routeProviders)->toHaveCount(1);
});

test('het Laravel 10-restant RouteServiceProvider bestaat niet meer', function () {
    expect(file_exists(base_path('app/Providers/RouteServiceProvider.php')))->toBeFalse();
});

test('elke routenaam komt maar één keer voor', function () {
    $names = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route): ?string => $route->getName())
        ->filter();

    expect($names->duplicates())->toBeEmpty();
});

test('de api-routes staan onder /api met de api-middlewaregroep', function () {
    $route = Route::getRoutes()->getByName('api.health');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('api/health')
        ->and($route->gatherMiddleware())->toContain('api')
        ->and($route->gatherMiddleware())->not->toContain('web');
});

test('de webroutes hangen in de web-middlewaregroep', function (string $name) {
    expect(Route::getRoutes()->getByName($name)?->gatherMiddleware())->toContain('web');
})->with(['welcome', 'contact', 'login', 'health', 'exports.sessions.download']);

test('/api/health antwoordt via de gedeelde health-controller', function () {
    $this->getJson('/api/health')->assertJsonStructure(['status']);
});

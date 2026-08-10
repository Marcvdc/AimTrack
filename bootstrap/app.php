<?php

use App\Http\Middleware\InjectUserAnthropicKey;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\BroadcastServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\FeatureServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        AppServiceProvider::class,
        AuthServiceProvider::class,
        BroadcastServiceProvider::class,
        EventServiceProvider::class,
        RouteServiceProvider::class,
        AdminPanelProvider::class,
        FeatureServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // TrustHosts zit NIET standaard in de globale stack (zie
        // Illuminate\Foundation\Configuration\Middleware::getGlobalMiddleware);
        // hij wordt pas toegevoegd door deze aanroep. Zonder deze regel wordt de
        // Host-header nergens gevalideerd en neemt route() de door de client
        // opgegeven host over — inclusief de wachtwoord-herstellink.
        // Zonder argumenten vertrouwt hij APP_URL en subdomeinen daarvan, lazy
        // uitgelezen op requestmoment.
        $middleware->trustHosts();

        // De framework-TrustProxies staat wél altijd in de stack, maar vertrouwt
        // zonder configuratie niets. Het adressenbereik komt uit
        // config/trustedproxy.php, dat Illuminate\Http\Middleware\TrustProxies
        // zelf uitleest — configuratie is hier nog niet geladen, dus die omweg
        // is bewust.

        // Injecteert de persoonlijke Claude-sleutel op het copilot/stream-pad.
        $middleware->web(append: [
            InjectUserAnthropicKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Customize exception handling here.
    })
    ->create();

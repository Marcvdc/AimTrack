<?php

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
        // Additional global middleware can be registered here.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Customize exception handling here.
    })
    ->create();

<?php

namespace App\Providers;

use App\Services\Ai\ShooterCoach;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ShooterCoach::class, fn () => ShooterCoach::make());
    }

    public function boot(): void
    {
        //
    }
}

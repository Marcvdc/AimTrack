<?php

namespace App\Providers;

use App\Services\Ai\ShooterCoach;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ShooterCoach::class, fn () => ShooterCoach::make());
    }

    public function boot(): void
    {
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        if ($this->app->environment('production')) {
            Config::set('app.debug', false);
        }

        Queue::failing(function (JobFailed $event): void {
            Log::error('Queue job mislukt', [
                'job' => $event->job?->resolveName(),
                'queue' => $event->job?->getQueue(),
                'connection' => $event->connectionName,
                'exception' => $event->exception->getMessage(),
            ]);

            if (app()->bound('sentry')) {
                app('sentry')->captureException($event->exception);
            }
        });
    }
}

<?php

namespace App\Providers;

use App\Http\Responses\Auth\AdminLogoutResponse;
use App\Services\Ai\ShooterCoach;
use App\Support\DateFormat;
use App\Support\Features\AimtrackFeatureToggle;
use App\Support\StoragePathInitializer;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Table;
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
        $this->app->singleton(AimtrackFeatureToggle::class);

        $this->app->bind(LogoutResponseContract::class, AdminLogoutResponse::class);
    }

    public function boot(): void
    {
        StoragePathInitializer::ensure();

        $this->configureDateDisplay();

        if (config('app.force_https')) {
            URL::forceScheme('https');
        } elseif ($this->app->environment('local')) {
            URL::forceScheme('http');
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

    /**
     * Zonder deze configuratie valt elke kale ->date()-aanroep terug op
     * Filaments eigen default "M j, Y": de Amerikaanse notatie, pal naast
     * handmatig gezette d-m-Y-kolommen elders in dezelfde app.
     *
     * De tijdzone wordt alleen voor de weergave gezet. config('app.timezone')
     * blijft UTC, zodat bestaande rijen niet stilletjes verschuiven; Filament
     * gebruikt FilamentTimezone uitsluitend voor datetime-weergave en laat
     * kale datumkolommen met rust.
     */
    private function configureDateDisplay(): void
    {
        FilamentTimezone::set(DateFormat::displayTimezone());

        Table::configureUsing(fn (Table $table): Table => $table
            ->defaultDateDisplayFormat(DateFormat::DATE)
            ->defaultDateTimeDisplayFormat(DateFormat::DATE_TIME)
            ->defaultTimeDisplayFormat(DateFormat::TIME));

        Schema::configureUsing(fn (Schema $schema): Schema => $schema
            ->defaultDateDisplayFormat(DateFormat::DATE)
            ->defaultDateTimeDisplayFormat(DateFormat::DATE_TIME)
            ->defaultTimeDisplayFormat(DateFormat::TIME));
    }
}

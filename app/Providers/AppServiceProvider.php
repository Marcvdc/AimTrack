<?php

namespace App\Providers;

use App\Http\Responses\Auth\AdminLogoutResponse;
use App\Services\Ai\ShooterCoach;
use App\Support\DateFormat;
use App\Support\Features\AimtrackFeatureToggle;
use App\Support\StoragePathInitializer;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Filament\Forms\Components\DateTimePicker;
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

            report($event->exception);
        });
    }

    /**
     * Zonder deze configuratie valt elke kale ->date()-aanroep terug op
     * Filaments eigen default "M j, Y": de Amerikaanse notatie, pal naast
     * handmatig gezette d-m-Y-kolommen elders in dezelfde app.
     *
     * Er zijn drie plekken nodig, geen twee. Tabelkolommen en infolist-entries
     * halen hun format bij de container op (Table respectievelijk Schema),
     * maar Filament\Forms\Components\DateTimePicker draagt zijn eigen
     * defaultDateDisplayFormat op de klasse en raadpleegt de container niet.
     * Een DatePicker binnen een schema dat op d-m-Y staat rendert daardoor
     * alsnog "MMM D, YYYY". DatePicker en TimePicker erven van DateTimePicker
     * en ComponentManager::configure() loopt de ouderklassen af, dus die ene
     * configureUsing dekt alle drie de kiezers.
     *
     * De tijdzone wordt alleen voor de weergave gezet. config('app.timezone')
     * blijft UTC, zodat bestaande rijen niet stilletjes verschuiven. Let op:
     * FilamentTimezone raakt ook kale datumkolommen, want TextColumn::date()
     * doet setTimezone() op de kolomtijdzone. Dat gaat hier goed omdat
     * Europe/Amsterdam oostelijk van UTC ligt (00:00 schuift vooruit binnen
     * dezelfde dag); met een westelijke APP_DISPLAY_TIMEZONE zou elke
     * datumkolom een dag te vroeg renderen.
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

        DateTimePicker::configureUsing(fn (DateTimePicker $picker): DateTimePicker => $picker
            ->defaultDateDisplayFormat(DateFormat::DATE)
            ->defaultDateTimeDisplayFormat(DateFormat::DATE_TIME)
            ->defaultDateTimeWithSecondsDisplayFormat(DateFormat::DATE_TIME_SECONDS)
            ->defaultTimeDisplayFormat(DateFormat::TIME)
            ->defaultTimeWithSecondsDisplayFormat(DateFormat::TIME_SECONDS));
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\UserOnboardingState;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * Custom Dashboard die de standaard widgets-grid vervangt door een
 * full-page "First-run welcome" wanneer de ingelogde gebruiker nog
 * geen sessies én geen wapens heeft. Zodra de gebruiker iets aanmaakt,
 * valt de pagina terug op de oorspronkelijke widgets-grid.
 *
 * PLAN-fase-2 AC2 + AC7.
 */
class Dashboard extends BaseDashboard
{
    private ?UserOnboardingState $onboarding = null;

    public function getOnboarding(): UserOnboardingState
    {
        /** @var User $user */
        $user = Auth::user();

        return $this->onboarding ??= new UserOnboardingState($user);
    }

    public function content(Schema $schema): Schema
    {
        if ($this->getOnboarding()->hasNoData()) {
            return $schema->components([
                View::make('filament.pages.partials.first-run-welcome'),
            ]);
        }

        return parent::content($schema);
    }

    public function getTitle(): string
    {
        if ($this->getOnboarding()->hasNoData()) {
            return 'Welkom bij AimTrack';
        }

        return parent::getTitle();
    }

    /**
     * Livewire-action getriggerd door "Demo-data inladen". Echte
     * seed-logica wordt in Fase 2 Stap 6 (SeedDemoDataAction) toegevoegd
     * — voor nu een placeholder zodat de welcome-CTA niet stuk lijkt.
     */
    public function seedDemoData(): void
    {
        Notification::make()
            ->title('Demo-data binnenkort beschikbaar')
            ->body('De seed-functie wordt in een aparte stap toegevoegd. Voor nu kun je je eerste wapen handmatig aanmaken.')
            ->info()
            ->send();
    }
}

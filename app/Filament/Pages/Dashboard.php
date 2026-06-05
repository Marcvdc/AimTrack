<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\HasSeedDemoDataAction;
use App\Models\User;
use App\Services\RangeConsoleSummaryService;
use App\Services\TrainingGoalService;
use App\Support\UserOnboardingState;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
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
    use HasSeedDemoDataAction;

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

        return $schema->components([
            View::make('filament.pages.dashboard-overview'),
        ]);
    }

    public function getSummary(): RangeConsoleSummaryService
    {
        /** @var User $user */
        $user = Auth::user();

        return new RangeConsoleSummaryService($user);
    }

    /**
     * @return Collection<int, \App\Models\TrainingGoal>
     */
    public function getTrainingGoals(): Collection
    {
        /** @var User $user */
        $user = Auth::user();

        return app(TrainingGoalService::class)->openGoals($user, 4);
    }

    /**
     * Gecureerde KNSA-kennisbank-links (gedeeld met de landingspagina).
     *
     * @return array<int, array{title: string, description?: string, url: string}>
     */
    public function getKnsaLinks(): array
    {
        return config('landing.knsa_links', []);
    }

    public function getTitle(): string
    {
        if ($this->getOnboarding()->hasNoData()) {
            return 'Welkom bij AimTrack';
        }

        return parent::getTitle();
    }
}

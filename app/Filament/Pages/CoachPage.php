<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Copilot\Tools\ShooterContextTool;
use App\Filament\Resources\SessionResource;
use App\Models\User;
use App\Services\CoachContextService;
use App\Support\Features\AimtrackFeatureToggle;
use App\Support\UserOnboardingState;
use BackedEnum;
use EslamRedaDiv\FilamentCopilot\Contracts\CopilotPage as CopilotPageContract;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CoachPage extends Page implements CopilotPageContract
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'AI-coach';

    protected static ?string $title = 'AI-coach';

    protected static string|UnitEnum|null $navigationGroup = 'AI & inzichten';

    protected string $view = 'filament.pages.coach-page';

    public static function shouldRegisterNavigation(): bool
    {
        return static::features()->aiEnabled() && parent::shouldRegisterNavigation();
    }

    public function getCoachContext(): CoachContextService
    {
        return new CoachContextService(auth()->user());
    }

    public static function copilotPageDescription(): ?string
    {
        return 'AI-coach landingspagina. Hier stelt de schutter open vragen aan de copilot over training, sessies, wapens en techniek. Gebruik de ShooterContextTool om relevante schiet-data op te halen.';
    }

    public static function copilotTools(): array
    {
        return [
            new ShooterContextTool,
        ];
    }

    protected static function features(): AimtrackFeatureToggle
    {
        return app(AimtrackFeatureToggle::class);
    }

    private ?UserOnboardingState $onboarding = null;

    public function getOnboarding(): UserOnboardingState
    {
        /** @var User $user */
        $user = Auth::user();

        return $this->onboarding ??= new UserOnboardingState($user);
    }

    public function getCreateSessionUrl(): string
    {
        return SessionResource::getUrl('create');
    }

    /**
     * Livewire-action voor de "Hoe werkt de AI?"-knop in de empty state.
     * Toont uitleg via een notification — voor users die nog onder de
     * drempel zitten en daarom de chat-UI niet zien.
     */
    public function explainAiCoach(): void
    {
        $threshold = UserOnboardingState::aiCoachThreshold();

        Notification::make()
            ->title('Hoe werkt de AI-coach?')
            ->body("De coach analyseert je gelogde sessies (afwijkingen, groepering, ademhalingsritmes) en suggereert verbeterpunten. Vanaf {$threshold} sessies is er genoeg patroon om zinvolle feedback te geven.")
            ->info()
            ->persistent()
            ->send();
    }
}

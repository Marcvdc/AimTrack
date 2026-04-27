<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Copilot\Tools\ShooterContextTool;
use App\Support\Features\AimtrackFeatureToggle;
use BackedEnum;
use EslamRedaDiv\FilamentCopilot\Contracts\CopilotPage as CopilotPageContract;
use Filament\Pages\Page;
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
}

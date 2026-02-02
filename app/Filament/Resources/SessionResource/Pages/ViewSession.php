<?php

namespace App\Filament\Resources\SessionResource\Pages;

use App\Filament\Resources\SessionResource;
use App\Jobs\GenerateSessionReflectionJob;
use App\Support\Features\AimtrackFeatureToggle;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;

class ViewSession extends ViewRecord
{
    protected static string $resource = SessionResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Bewerken'),
            Actions\Action::make('regenerateAi')
                ->label('Genereer AI-reflectie nu')
                ->tooltip('BETA')
                ->icon('heroicon-m-sparkles')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function (): void {
                    if ($this->features()->aiDisabled()) {
                        Notification::make()
                            ->title('AI-functies uitgeschakeld')
                            ->body('Schakel de featureflag aimtrack-ai in om AI-reflecties te genereren.')
                            ->warning()
                            ->send();

                        return;
                    }

                    GenerateSessionReflectionJob::dispatch($this->record);

                    Notification::make()
                        ->title('AI-reflectie opnieuw ingepland')
                        ->body('De taak is in de wachtrij geplaatst.')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function features(): AimtrackFeatureToggle
    {
        return app(AimtrackFeatureToggle::class);
    }
}

<?php

namespace App\Filament\Resources\SessionResource\Pages;

use App\Filament\Resources\SessionResource;
use App\Jobs\GenerateSessionReflectionJob;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSession extends ViewRecord
{
    protected static string $resource = SessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Bewerken'),
            Actions\Action::make('regenerateAi')
                ->label('AI-reflectie opnieuw genereren')
                ->icon('heroicon-m-sparkles')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function (): void {
                    GenerateSessionReflectionJob::dispatch($this->record);

                    Notification::make()
                        ->title('AI-reflectie opnieuw ingepland')
                        ->body('De taak is in de wachtrij geplaatst.')
                        ->success()
                        ->send();
                }),
        ];
    }
}

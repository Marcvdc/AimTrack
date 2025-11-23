<?php

namespace App\Filament\Resources\WeaponResource\Pages;

use App\Filament\Resources\WeaponResource;
use App\Jobs\GenerateWeaponInsightJob;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewWeapon extends ViewRecord
{
    protected static string $resource = WeaponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Bewerken'),
            Actions\Action::make('generateAi')
                ->label('Genereer AI-inzichten nu')
                ->icon('heroicon-m-sparkles')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function (): void {
                    GenerateWeaponInsightJob::dispatch($this->record);

                    Notification::make()
                        ->title('AI-inzichten ingepland')
                        ->body('De job is aan de queue toegevoegd.')
                        ->success()
                        ->send();
                }),
        ];
    }
}

<?php

namespace App\Filament\Resources\SessionResource\Pages;

use App\Filament\Resources\SessionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSession extends EditRecord
{
    protected static string $resource = SessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Verwijderen'),
            Actions\Action::make('manageShots')
                ->label('Schoten registreren')
                ->icon('heroicon-o-sparkles')
                ->url(fn (): string => static::getResource()::getUrl('shots', ['record' => $this->getRecord()])),
        ];
    }
}

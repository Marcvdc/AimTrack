<?php

namespace App\Filament\Resources\SessionResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\SessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSessions extends ListRecords
{
    protected static string $resource = SessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nieuwe sessie'),
        ];
    }
}

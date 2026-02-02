<?php

namespace App\Filament\Resources\LocationResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\LocationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLocations extends ListRecords
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nieuwe locatie'),
        ];
    }
}

<?php

namespace App\Filament\Resources\AmmoTypeResource\Pages;

use App\Filament\Resources\AmmoTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAmmoTypes extends ListRecords
{
    protected static string $resource = AmmoTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nieuw munitietype'),
        ];
    }
}

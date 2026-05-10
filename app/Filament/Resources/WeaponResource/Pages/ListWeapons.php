<?php

namespace App\Filament\Resources\WeaponResource\Pages;

use App\Filament\Resources\WeaponResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWeapons extends ListRecords
{
    protected static string $resource = WeaponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nieuw wapen'),
        ];
    }
}

<?php

namespace App\Filament\Resources\WeaponResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\WeaponResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWeapon extends EditRecord
{
    protected static string $resource = WeaponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Verwijderen'),
        ];
    }
}

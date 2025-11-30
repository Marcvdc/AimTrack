<?php

namespace App\Filament\Resources\AmmoTypeResource\Pages;

use App\Filament\Resources\AmmoTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAmmoType extends EditRecord
{
    protected static string $resource = AmmoTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Verwijderen'),
        ];
    }
}

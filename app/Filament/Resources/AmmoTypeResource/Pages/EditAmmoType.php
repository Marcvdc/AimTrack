<?php

namespace App\Filament\Resources\AmmoTypeResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\AmmoTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAmmoType extends EditRecord
{
    protected static string $resource = AmmoTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Verwijderen'),
        ];
    }
}

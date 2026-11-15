<?php

namespace App\Filament\Resources\AmmoTypeResource\Pages;

use App\Filament\Concerns\AssignsOwnerOnCreate;
use App\Filament\Resources\AmmoTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAmmoType extends CreateRecord
{
    use AssignsOwnerOnCreate;

    protected static string $resource = AmmoTypeResource::class;
}

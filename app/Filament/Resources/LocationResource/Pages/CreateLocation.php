<?php

namespace App\Filament\Resources\LocationResource\Pages;

use App\Filament\Concerns\AssignsOwnerOnCreate;
use App\Filament\Resources\LocationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLocation extends CreateRecord
{
    use AssignsOwnerOnCreate;

    protected static string $resource = LocationResource::class;
}

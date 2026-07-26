<?php

namespace App\Filament\Resources\CoachSessies\Pages;

use App\Filament\Resources\CoachSessies\CoachSessieResource;
use Filament\Resources\Pages\ListRecords;

class ListCoachSessies extends ListRecords
{
    protected static string $resource = CoachSessieResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

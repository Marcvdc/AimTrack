<?php

namespace App\Filament\Resources\CoachSessies\Pages;

use App\Filament\Resources\CoachSessies\CoachSessieResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCoachSessie extends ViewRecord
{
    protected static string $resource = CoachSessieResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

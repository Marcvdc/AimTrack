<?php

declare(strict_types=1);

namespace App\Filament\Resources\SessionResource\Pages;

use App\Filament\Resources\SessionResource;
use App\Services\RangeConsoleSummaryService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSessions extends ListRecords
{
    protected static string $resource = SessionResource::class;

    protected string $view = 'filament.resources.sessions.list-sessions';

    public function getRangeConsoleSummary(): RangeConsoleSummaryService
    {
        return new RangeConsoleSummaryService(auth()->user());
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nieuwe sessie'),
        ];
    }
}

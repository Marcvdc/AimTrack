<?php

namespace App\Filament\Resources\SessionResource\Pages;

use App\Filament\Resources\SessionResource;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSessions extends ListRecords
{
    protected static string $resource = SessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nieuwe sessie'),
        ];
    }

    /**
     * Livewire-action getriggerd door de "Demo-data inladen"-knop in de
     * empty state. Daadwerkelijke seed-logica wordt in Fase 2 Stap 6
     * (SeedDemoDataAction) toegevoegd — voor nu een placeholder zodat
     * de CTA niet stuk lijkt.
     */
    public function seedDemoData(): void
    {
        Notification::make()
            ->title('Demo-data binnenkort beschikbaar')
            ->body('De seed-functie wordt in een aparte stap toegevoegd. Je kunt je eerste sessie handmatig aanmaken via "Nieuwe sessie".')
            ->info()
            ->send();
    }
}

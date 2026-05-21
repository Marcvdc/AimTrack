<?php

namespace App\Filament\Resources\WeaponResource\Pages;

use App\Filament\Resources\WeaponResource;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
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
            ->body('De seed-functie wordt in een aparte stap toegevoegd. Je kunt een wapen direct toevoegen via een sjabloon of "Voeg wapen toe".')
            ->info()
            ->send();
    }
}

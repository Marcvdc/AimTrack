<?php

declare(strict_types=1);

namespace App\Filament\Resources\SessionResource\Pages;

use App\Filament\Resources\SessionResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;

class CreateSession extends CreateRecord
{
    use HasWizard;

    protected static string $resource = SessionResource::class;

    /**
     * Range Console nieuwe-sessie wizard — 4 stappen conform
     * new-session-wizard.jsx (decision 5: in-page Filament Wizard).
     *
     * @return array<int, Step>
     */
    public function getSteps(): array
    {
        return [
            Step::make('Wapen')
                ->icon(Heroicon::OutlinedBolt)
                ->description('Welke wapens gebruik je?')
                ->schema([
                    SessionResource::userIdField(),
                    SessionResource::sessionWeaponsRepeater(),
                ]),

            Step::make('Sessie')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->description('Datum, baan en locatie')
                ->columns(2)
                ->schema(SessionResource::sessionDetailFields()),

            Step::make('Schoten')
                ->icon(Heroicon::OutlinedViewfinderCircle)
                ->description('Log je schoten na het opslaan')
                ->schema([
                    ViewComponent::make('filament.resources.sessions.wizard-shots-step'),
                ]),

            Step::make('Notities')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->description('Reflectie en bijlagen')
                ->schema([
                    ...SessionResource::notesFields(),
                    SessionResource::attachmentsRepeater(),
                ]),
        ];
    }

    /**
     * Na afronden direct naar het schotenbord: daar logt de schutter de
     * werkelijke schoten (de board vereist een opgeslagen sessie).
     */
    protected function getRedirectUrl(): string
    {
        return SessionResource::getUrl('shots', ['record' => $this->getRecord()]);
    }
}

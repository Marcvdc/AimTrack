<?php

namespace App\Filament\Resources\SessionResource\Pages;

use App\Filament\Resources\SessionResource;
use App\Livewire\SessionShotBoard;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ManageSessionShots extends Page
{
    use InteractsWithRecord;

    protected static string $resource = SessionResource::class;

    protected string $view = 'filament.resources.session-resource.pages.manage-session-shots';

    protected static ?string $title = 'Schoten registreren';

    protected static bool $shouldRegisterNavigation = false;

    protected ?string $subheading = 'Leg schoten vast en beheer beurten';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizeAccess();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Terug naar sessie')
                ->icon('heroicon-m-arrow-uturn-left')
                ->url($this->getResource()::getUrl('edit', ['record' => $this->record])),
        ];
    }

    /**
     * Get a unique color for each turn (synchronized with SessionShotBoard)
     */
    public function getTurnColor(int $turnIndex): string
    {
        // Use the same color palette as SessionShotBoard
        $colors = [
            '#0ea5e9', // sky
            '#a855f7', // purple
            '#f97316', // orange
            '#ef4444', // red
            '#10b981', // emerald
            '#f59e0b', // amber
            '#8b5cf6', // violet
            '#ec4899', // pink
            '#14b8a6', // teal
            '#6366f1', // indigo
        ];

        return $colors[$turnIndex % count($colors)];
    }
}

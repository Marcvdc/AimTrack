<?php

namespace App\Filament\Resources\SessionResource\Pages;

use App\Filament\Resources\SessionResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ManageSessionShots extends Page
{
    use InteractsWithRecord;

    protected static string $resource = SessionResource::class;

    protected string $view = 'filament.resources.session-resource.pages.manage-session-shots';

    protected static ?string $title = 'Schoten registreren';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-bullseye';

    protected bool $shouldAuthorizeAccess = true;

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
}

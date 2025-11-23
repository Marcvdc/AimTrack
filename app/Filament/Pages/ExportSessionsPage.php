<?php

namespace App\Filament\Pages;

use App\Models\Weapon;
use App\Services\Export\SessionExportService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use UnitEnum;

class ExportSessionsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationLabel = 'Export sessies';

    protected static ?string $title = 'Export sessies';

    protected static UnitEnum|string|null $navigationGroup = 'Exports & rapportage';

    protected string $view = 'filament.pages.export-sessions-page';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'from_date' => now()->startOfMonth(),
            'to_date' => now(),
            'format' => 'csv',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Parameters')
                    ->schema([
                        DatePicker::make('from_date')
                            ->label('Vanaf datum')
                            ->native(false)
                            ->required(),
                        DatePicker::make('to_date')
                            ->label('Tot datum')
                            ->native(false)
                            ->required(),
                        Select::make('weapon_ids')
                            ->label('Wapens (optioneel)')
                            ->multiple()
                            ->searchable()
                            ->options(fn () => $this->weaponOptions())
                            ->helperText('Laat leeg om alle wapens op te nemen.'),
                        Select::make('format')
                            ->label('Formaat')
                            ->options([
                                'csv' => 'CSV',
                                'pdf' => 'PDF',
                            ])
                            ->required(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function submit(SessionExportService $service)
    {
        $state = $this->form->getState();

        $from = Carbon::parse($state['from_date']);
        $to = Carbon::parse($state['to_date']);

        if ($from->greaterThan($to)) {
            Notification::make()
                ->title('Ongeldige periode')
                ->body('De startdatum moet voor of gelijk aan de einddatum zijn.')
                ->danger()
                ->send();

            return null;
        }

        return $service->exportSessions(
            auth()->user(),
            $from,
            $to,
            $state['weapon_ids'] ?? null,
            $state['format'] ?? 'csv'
        );
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('export')
                ->label('Exporteren')
                ->submit('submit')
                ->icon('heroicon-m-arrow-down-tray'),
        ];
    }

    protected function weaponOptions(): Collection
    {
        return Weapon::query()
            ->where('user_id', auth()->id())
            ->orderBy('name')
            ->pluck('name', 'id');
    }
}

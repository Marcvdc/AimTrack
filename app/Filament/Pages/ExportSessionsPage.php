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
use Filament\Schemas\Components\Actions as ActionsSchema;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form as FormSchema;
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

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                FormSchema::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('submit')
                    ->footer([
                        ActionsSchema::make($this->getFormActions()),
                    ]),
            ]);
    }

    public function submit()
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

        $weaponIds = $state['weapon_ids'] ?? [];
        $weaponIdsParam = ! empty($weaponIds) ? implode(',', $weaponIds) : null;

        return redirect()->route('exports.sessions.download', array_filter([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'weapon_ids' => $weaponIdsParam,
            'format' => $state['format'] ?? 'csv',
        ], fn ($value) => $value !== null && $value !== ''));
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

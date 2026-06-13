<?php

namespace App\Filament\Pages;

use App\Enums\VerenigingRol;
use App\Models\User;
use App\Models\Vereniging;
use App\Services\Vereniging\VerenigingException;
use App\Services\Vereniging\VerenigingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VerenigingBeheer extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Mijn vereniging';

    protected static ?string $title = 'Mijn vereniging';

    protected static string|\UnitEnum|null $navigationGroup = 'BEHEER';

    protected string $view = 'filament.pages.vereniging-beheer';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return static::actieveVerenigingVoorBeheerder() !== null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill(['anthropic_api_key' => null]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Gedeelde Claude API-key')
                    ->schema([
                        TextInput::make('anthropic_api_key')
                            ->label('Claude API-key')
                            ->password()
                            ->revealable()
                            ->autocomplete(false)
                            ->placeholder($this->keyPlaceholder())
                            ->helperText('Gedeelde Anthropic-key voor leden zonder eigen key. Versleuteld opgeslagen, nooit volledig getoond. Laat leeg om de huidige key te behouden.'),
                    ]),
                Section::make('Lid toevoegen')
                    ->schema([
                        TextInput::make('email')
                            ->label('E-mailadres van een bestaand account')
                            ->email(),
                        Select::make('role')
                            ->label('Rol')
                            ->options(static::rolOpties())
                            ->default(VerenigingRol::Member->value),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => User::query()
                ->join('vereniging_user', 'vereniging_user.user_id', '=', 'users.id')
                ->where('vereniging_user.vereniging_id', $this->vereniging()->id)
                ->select('users.*', 'vereniging_user.role as role'))
            ->columns([
                TextColumn::make('name')->label('Naam')->searchable(),
                TextColumn::make('email')->label('E-mail')->searchable(),
                SelectColumn::make('role')
                    ->label('Rol')
                    ->options(static::rolOpties())
                    ->selectablePlaceholder(false)
                    ->updateStateUsing(fn (User $record, string $state) => $this->voerUit(
                        fn (VerenigingService $service) => $service->wijzigRol(
                            $this->vereniging(),
                            $record,
                            VerenigingRol::from($state),
                        ),
                        'Rol gewijzigd',
                    )),
            ])
            ->recordActions([
                Action::make('verwijderLid')
                    ->label('Verwijderen')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $this->voerUit(
                        fn (VerenigingService $service) => $service->verwijderLid($this->vereniging(), $record),
                        'Lid verwijderd',
                    )),
            ]);
    }

    public function voegLidToe(): void
    {
        $email = data_get($this->data, 'email');
        $role = data_get($this->data, 'role', VerenigingRol::Member->value);

        if (blank($email)) {
            Notification::make()->title('Vul een e-mailadres in')->warning()->send();

            return;
        }

        $this->voerUit(fn (VerenigingService $service) => $service->voegLidToe(
            $this->vereniging(),
            $email,
            VerenigingRol::from($role),
        ), 'Lid toegevoegd');

        $this->data['email'] = null;
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('testKey')
                ->label('Test key')
                ->icon('heroicon-m-bolt')
                ->action(function (): void {
                    $geldig = app(VerenigingService::class)->testKey($this->vereniging());

                    Notification::make()
                        ->title($geldig ? 'Key is geldig' : 'Key ongeldig of ontbreekt')
                        ->{$geldig ? 'success' : 'danger'}()
                        ->send();
                }),
            Action::make('wisKey')
                ->label('Wis key')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    app(VerenigingService::class)->wisKey($this->vereniging());
                    Notification::make()->title('Key gewist')->success()->send();
                }),
        ];
    }

    public function save(): void
    {
        $key = data_get($this->form->getState(), 'anthropic_api_key')
            ?? data_get($this->data, 'anthropic_api_key');

        if (blank($key)) {
            Notification::make()->title('Geen key ingevoerd')->warning()->send();

            return;
        }

        app(VerenigingService::class)->bewaarKey($this->vereniging(), $key);
        $this->form->fill(['anthropic_api_key' => null]);

        Notification::make()->title('Key opgeslagen')->success()->send();
    }

    public function vereniging(): Vereniging
    {
        return static::actieveVerenigingVoorBeheerder();
    }

    /**
     * @param  callable(VerenigingService):mixed  $callback
     */
    protected function voerUit(callable $callback, string $succesTitel): void
    {
        try {
            $callback(app(VerenigingService::class));
        } catch (VerenigingException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($succesTitel)->success()->send();
    }

    protected function keyPlaceholder(): string
    {
        $key = $this->vereniging()->anthropic_api_key;

        return filled($key) ? 'Huidige key: ••••'.substr($key, -4) : 'sk-ant-…';
    }

    /**
     * @return array<string, string>
     */
    protected static function rolOpties(): array
    {
        return collect(VerenigingRol::cases())
            ->mapWithKeys(fn (VerenigingRol $rol): array => [$rol->value => $rol->label()])
            ->all();
    }

    protected static function actieveVerenigingVoorBeheerder(): ?Vereniging
    {
        $user = auth()->user();
        $vereniging = $user?->activeVereniging;

        if ($vereniging === null) {
            return null;
        }

        return ($user->rolInVereniging($vereniging)?->canManage() ?? false)
            ? $vereniging
            : null;
    }
}

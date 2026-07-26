<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\Vereniging\VerenigingException;
use App\Services\Vereniging\VerenigingService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Aanmaak-pagina (BEHEER) voor verenigingen. Alleen app-beheerders
 * (users.is_admin) mogen verenigingen provisionen en een beheerder toewijzen;
 * die beheerder regelt daarna leden en de gedeelde key via "Mijn vereniging".
 */
class VerenigingAanmaken extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Vereniging aanmaken';

    protected static ?string $title = 'Vereniging aanmaken';

    protected static string|\UnitEnum|null $navigationGroup = 'BEHEER';

    protected static ?int $navigationSort = 80;

    protected string $view = 'filament.pages.vereniging-aanmaken';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) (auth()->user()?->is_admin);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Nieuwe vereniging')
                    ->schema([
                        TextInput::make('naam')
                            ->label('Naam')
                            ->required()
                            ->maxLength(255),
                        Select::make('beheerder_id')
                            ->label('Beheerder')
                            ->helperText('Deze gebruiker wordt admin van de vereniging en beheert de leden en de gedeelde key.')
                            ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function aanmaken(): void
    {
        $state = $this->form->getState();

        $beheerder = User::query()->find($state['beheerder_id'] ?? null);

        if ($beheerder === null) {
            Notification::make()->title('Kies een geldige beheerder')->warning()->send();

            return;
        }

        try {
            $vereniging = app(VerenigingService::class)->maakVereniging($state['naam'], $beheerder);
        } catch (VerenigingException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        $this->form->fill();

        Notification::make()
            ->title("Vereniging '{$vereniging->naam}' aangemaakt")
            ->body("{$beheerder->name} is als beheerder toegevoegd.")
            ->success()
            ->send();
    }
}

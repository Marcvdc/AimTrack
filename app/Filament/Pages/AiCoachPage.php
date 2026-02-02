<?php

namespace App\Filament\Pages;

use App\Models\CoachQuestion;
use App\Models\Weapon;
use App\Services\Ai\ShooterCoach;
use App\Support\Features\AimtrackFeatureToggle;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Actions;
use Filament\Actions\Action as FormAction;
use Filament\Actions\DeleteAction as TablesDeleteAction;
use Filament\Schemas\Components\Actions as SchemaActions;

class AiCoachPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'AI-coach';

    protected static ?string $title = 'AI-coach';

    protected static string | \UnitEnum | null $navigationGroup = 'AI & inzichten';

    protected string $view = 'filament.pages.ai-coach-page';

    public ?array $data = [];

    public ?string $answer = null;

    public Collection $history;

    public ?string $currentAnswer = null;

    public static function shouldRegisterNavigation(): bool
    {
        return static::features()->aiEnabled() && parent::shouldRegisterNavigation();
    }

    public function mount(): void
    {
        abort_if(static::features()->aiDisabled(), 403, 'AI-functies zijn uitgeschakeld.');

        $this->form->fill([
            'question' => '',
            'weapon_id' => null,
            'from_date' => null,
            'to_date' => null,
        ]);

        $this->loadHistory();
    }

    protected static function features(): AimtrackFeatureToggle
    {
        return app(AimtrackFeatureToggle::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Stel je vraag')
                    ->description('De AI-coach gebruikt alleen jouw eigen logs als context en geeft geen vervanging voor erkende instructeurs of officiële regels.')
                    ->schema([
                        Textarea::make('question')
                            ->label('Vraag aan AI-coach')
                            ->placeholder('Bijvoorbeeld: hoe kan ik mijn consistentie op 25m met mijn pistool verbeteren?')
                            ->rows(5)
                            ->required(),
                        Section::make('Context (optioneel)')
                            ->schema([
                                Select::make('weapon_id')
                                    ->label('Specifiek wapen')
                                    ->native(false)
                                    ->options(fn () => $this->weaponOptions())
                                    ->searchable()
                                    ->placeholder('Alle wapens'),
                                DatePicker::make('from_date')
                                    ->label('Vanaf datum')
                                    ->native(false),
                                DatePicker::make('to_date')
                                    ->label('Tot datum')
                                    ->native(false),
                            ])
                            ->columns(3),
                        
                        SchemaActions::make([
                            \Filament\Actions\Action::make('ask')
                                ->label('Stel vraag aan AI-coach')
                                ->action('handleAskQuestion')
                                ->icon('heroicon-m-sparkles')
                                ->color('primary')
                                ->size('md'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CoachQuestion::query()
                    ->with('weapon')
                    ->where('user_id', auth()->id())
                    ->latest('asked_at')
            )
            ->columns([
                TextColumn::make('asked_at')
                    ->label('Datum')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->size('sm'),
                TextColumn::make('question')
                    ->label('Vraag')
                    ->limit(80)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('weapon.name')
                    ->label('Wapen')
                    ->sortable()
                    ->default('Alle wapens')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('answer')
                    ->label('AI Antwoord')
                    ->limit(100)
                    ->markdown()
                    ->searchable()
                    ->wrap(),
            ])
            ->defaultPaginationPageOption(5)
            ->paginated([5, 10, 25])
            ->striped()
            ->heading('Historie')
            ->description('Jouw recente vragen en AI-antwoorden')
            ->actions([
                TablesDeleteAction::make('delete')
                    ->label('Verwijder')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->modalHeading('Verwijder vraag')
                    ->modalDescription('Weet je zeker dat je deze vraag en het AI-antwoord wilt verwijderen?')
                    ->modalSubmitActionLabel('Verwijderen')
                    ->modalCancelActionLabel('Annuleren')
                    ->successNotificationTitle('Vraag verwijderd'),
            ]);
    }

    public function submit(ShooterCoach $coach): void
    {
        if (static::features()->aiDisabled()) {
            Notification::make()
                ->title('AI-functies uitgeschakeld')
                ->body('Schakel de featureflag aimtrack-ai in om vragen aan de AI-coach te stellen.')
                ->warning()
                ->send();

            return;
        }

        $state = $this->form->getState();

        $from = isset($state['from_date']) && $state['from_date'] ? Carbon::parse($state['from_date']) : null;
        $to = isset($state['to_date']) && $state['to_date'] ? Carbon::parse($state['to_date']) : null;

        if ($from && $to && $from->greaterThan($to)) {
            Notification::make()
                ->title('Ongeldige periode')
                ->body('De startdatum moet voor of gelijk aan de einddatum zijn.')
                ->danger()
                ->send();

            return;
        }

        $user = auth()->user();
        $question = (string) ($state['question'] ?? '');
        $weaponId = $state['weapon_id'] ?? null;

        $this->answer = $coach->answerCoachQuestion($user, $question, $weaponId, $from, $to);

        CoachQuestion::create([
            'user_id' => $user->id,
            'weapon_id' => $weaponId ?: null,
            'question' => $question,
            'answer' => $this->answer,
            'asked_at' => now(),
            'period_from' => $from,
            'period_to' => $to,
        ]);

        Notification::make()
            ->title('AI-coach antwoord klaar')
            ->success()
            ->send();

        $this->loadHistory();
    }

    public function handleAskQuestion(): void
    {
        $this->submit(app(\App\Services\Ai\ShooterCoach::class));
    }

    protected function weaponOptions(): Collection
    {
        return Weapon::query()
            ->where('user_id', auth()->id())
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    protected function loadHistory(): void
    {
        $this->history = CoachQuestion::query()
            ->with('weapon')
            ->where('user_id', auth()->id())
            ->latest('asked_at')
            ->latest()
            ->take(10)
            ->get();
    }
}

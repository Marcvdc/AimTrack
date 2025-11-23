<?php

namespace App\Filament\Pages;

use App\Models\CoachQuestion;
use App\Models\Weapon;
use App\Services\Ai\ShooterCoach;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use UnitEnum;

class AiCoachPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'AI-coach';

    protected static ?string $title = 'AI-coach';

    protected static UnitEnum|string|null $navigationGroup = 'AI & inzichten';

    protected string $view = 'filament.pages.ai-coach-page';

    public ?array $data = [];

    public ?string $answer = null;

    public Collection $history;

    public function mount(): void
    {
        $this->form->fill([
            'question' => '',
            'weapon_id' => null,
            'from_date' => null,
            'to_date' => null,
        ]);

        $this->loadHistory();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
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
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(ShooterCoach $coach): void
    {
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

    protected function getFormActions(): array
    {
        return [
            Action::make('ask')
                ->label('Stel vraag aan AI-coach')
                ->submit('submit')
                ->icon('heroicon-m-sparkles'),
        ];
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

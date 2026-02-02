<?php

namespace App\Filament\Pages;

use App\Models\CoachQuestion;
use App\Models\CoachSession;
use App\Models\Weapon;
use App\Services\Ai\ShooterCoach;
use App\Support\Features\AimtrackFeatureToggle;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Grid as FormGrid;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid as SchemaGrid;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action as FilamentAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use UnitEnum;

class AiCoachPage extends Page implements HasForms, HasTable, HasActions
{
    use InteractsWithForms, InteractsWithTable, InteractsWithActions;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'AI-coach (BETA)';

    protected static ?string $title = 'AI-coach (BETA)';

    protected static UnitEnum|string|null $navigationGroup = 'AI & inzichten';

    protected string $view = 'filament.pages.ai-coach-page';

    public ?array $data = [];

    public ?string $answer = null;

    public Collection $history;

    public Collection $filteredHistory;

    public Collection $currentSession;

    public Collection $oldSessions;

    public ?string $currentSessionId = null;

    public ?string $filterWeapon = null;

    public ?string $filterPeriod = null;

    public ?string $filterFromDate = null;

    public ?string $filterToDate = null;

    public ?int $sessionsPerPage = 10;

    public ?int $sessionsPage = 1;

    public bool $isProcessing = false;

    public string $contextInfo = 'Alle wapens · 01-01-2026 → nu';

    public static function shouldRegisterNavigation(): bool
    {
        return static::features()->aiEnabled() && parent::shouldRegisterNavigation();
    }

    public function mount(): void
    {
        $this->form->fill();
        $this->loadHistory();
        $this->startNewSession();
        $this->applyFilters();
        
        // Synchroniseer filterWeapon met data.weapon_id
        $this->filterWeapon = $this->data['weapon_id'] ?? null;
    }

    protected static function features(): AimtrackFeatureToggle
    {
        return app(AimtrackFeatureToggle::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Header
                Section::make('AI-coach')
                    ->description('Stel je vraag aan de AI-coach')
                    ->schema([
                        SchemaActions::make([
                            Action::make('startNewSession')
                                ->label('Nieuwe sessie')
                                ->icon('heroicon-o-plus')
                                ->action('startNewSession')
                                ->color('primary'),
                        ]),
                        
                        Placeholder::make('context_info')
                            ->content("ℹ Context: {$this->contextInfo}")
                            ->hidden(fn () => $this->contextInfo === 'Alle wapens · 01-01-2026 → nu'),
                        
                        Placeholder::make('chat_history')
                            ->content(function () {
                                if ($this->currentSession->isEmpty() && !$this->answer) {
                                    return '🤖 **AI-coach:** Welkom! Stel je vraag over training, techniek of logs.';
                                }
                                return '';
                            })
                            ->markdown()
                            ->hidden(fn () => !$this->currentSession->isEmpty() || !empty($this->answer)),
                        
                        Placeholder::make('current_session')
                            ->content(function () {
                                $content = '';
                                foreach ($this->currentSession as $item) {
                                    $content .= "👤 **Jouw vraag:** {$item->question}\n\n";
                                    $content .= "🤖 **AI-coach:** {$item->answer}\n\n";
                                    $content .= "---\n\n";
                                }
                                return $content;
                            })
                            ->markdown()
                            ->hidden(fn () => $this->currentSession->isEmpty()),
                        
                        Placeholder::make('current_answer')
                            ->content(fn () => "🤖 **AI-coach:** {$this->answer}")
                            ->markdown()
                            ->hidden(fn () => empty($this->answer)),
                        
                        Placeholder::make('typing')
                            ->content('🤖 AI is aan het typen...')
                            ->hidden(fn () => !$this->isProcessing),
                    ]),
                

                // Input Form met filters
                Section::make('Stel je vraag')
                    ->schema([
                        SchemaGrid::make(3)
                            ->schema([
                                Select::make('data.weapon_id')
                                    ->label('Wapen')
                                    ->options(fn () => $this->weaponOptions())
                                    ->placeholder('Alle wapens')
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->applyFilters()),
                                
                                Select::make('filterPeriod')
                                    ->label('Periode')
                                    ->options([
                                        'today' => 'Vandaag',
                                        'week' => 'Deze week', 
                                        'month' => 'Deze maand',
                                        'custom' => 'Custom',
                                    ])
                                    ->placeholder('Alle periodes')
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->applyFilters()),
                                
                                TextInput::make('filterFromDate')
                                    ->label('Vanaf datum')
                                    ->type('date')
                                    ->live()
                                    ->visible(fn ($get) => $get('filterPeriod') === 'custom')
                                    ->afterStateUpdated(fn () => $this->applyFilters()),
                            ]),
                        
                        SchemaGrid::make(2)
                            ->schema([
                                TextInput::make('filterToDate')
                                    ->label('Tot datum')
                                    ->type('date')
                                    ->live()
                                    ->visible(fn ($get) => $get('filterPeriod') === 'custom')
                                    ->afterStateUpdated(fn () => $this->applyFilters()),
                                
                                Textarea::make('data.question')
                                    ->label('Vraag')
                                    ->placeholder('Typ je vraag...')
                                    ->required()
                                    ->rows(2),
                            ]),
                        
                        SchemaActions::make([
                            Action::make('clearFilters')
                                ->label('Reset filters')
                                ->icon('heroicon-o-x-mark')
                                ->action('clearFilters')
                                ->color('gray'),
                            
                            Action::make('submit')
                                ->label('Verstuur')
                                ->icon('heroicon-o-paper-airplane')
                                ->action('submit')
                                ->disabled(fn () => $this->isProcessing),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getRecentQuestionsHtml(): string
    {
        if ($this->history->isEmpty()) {
            return '<p class="text-sm text-gray-600">Nog geen vragen gesteld.</p>';
        }

        $html = '<div class="space-y-3">';
        
        foreach ($this->history->take(5) as $item) {
            $html .= '<div class="rounded-md border border-gray-100 bg-gray-50 p-3">';
            $html .= '<div class="flex items-center justify-between text-xs text-gray-500">';
            $html .= '<span>' . $item->asked_at->format('d-m-Y H:i') . '</span>';
            if ($item->weapon) {
                $html .= '<span class="rounded bg-indigo-50 px-2 py-0.5 text-indigo-700">' . $item->weapon->name . '</span>';
            } else {
                $html .= '<span class="text-gray-500">Alle wapens</span>';
            }
            $html .= '</div>';
            $html .= '<p class="mt-2 text-sm font-semibold text-gray-800">Vraag (gebruiker)</p>';
            $html .= '<p class="text-sm text-gray-700">' . Str::limit($item->question, 100) . '</p>';
            $html .= '<p class="mt-2 text-sm font-semibold text-indigo-800">AI-antwoord</p>';
            $html .= '<p class="text-sm text-gray-700">' . Str::limit($item->answer, 150) . '</p>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        return $html;
    }

    protected function getAnswerHtml(): string
    {
        if (!$this->answer) {
            return '';
        }

        return '<div class="rounded-lg border border-indigo-100 bg-gradient-to-r from-indigo-50 to-blue-50 p-6 shadow-lg">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-indigo-100">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">AI-coach Antwoord</h2>
                                <p class="text-sm text-gray-600">Gepersonaliseerd advies gebaseerd op jouw sessiegegevens</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                            AI
                        </span>
                    </div>
                    
                    <div class="bg-white rounded-lg p-4 border border-indigo-100">
                        <div class="prose prose-sm max-w-none">
                            <p class="text-gray-800 leading-relaxed whitespace-pre-line">' . $this->answer . '</p>
                        </div>
                    </div>
                    
                    <div class="mt-4 flex items-center gap-2 text-xs text-gray-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>Dit advies is gebaseerd op jouw eigen sessiedata. Blijf zelf kritisch en veilig handelen.</span>
                    </div>
                </div>';
    }

    
    protected function getFormActions(): array
    {
        return [];
    }

    public function submit(): void
    {
        $this->isProcessing = true;
        
        $state = $this->form->getState();
        
        $executed = RateLimiter::attempt(
            'ai-coach-question:' . auth()->id(),
            3,
            fn () => $this->askQuestion($state)
        );

        if (!$executed) {
            Notification::make()
                ->title('Te veel vragen')
                ->body('Je mag maximaal 3 vragen per uur stellen.')
                ->warning()
                ->send();
                
            $this->isProcessing = false;
            return;
        }

        $this->form->fill();
        $this->loadHistory();
        $this->loadSessions(); // Herlaad sessies na nieuwe vraag
        $this->applyFilters();
        $this->isProcessing = false;
    }

    public function clearFilters(): void
    {
        $this->filterWeapon = null;
        $this->filterPeriod = null;
        $this->filterFromDate = null;
        $this->filterToDate = null;
        
        // Reset ook de form data
        $this->data['weapon_id'] = null;
        
        $this->applyFilters();
    }

    public function applyFilters(): void
    {
        // Synchroniseer filterWeapon met data.weapon_id
        $this->filterWeapon = $this->data['weapon_id'] ?? null;
        
        $query = CoachQuestion::query()
            ->with('weapon')
            ->where('user_id', auth()->id())
            ->latest('asked_at');

        // Apply period filter
        if ($this->filterPeriod === 'today') {
            $query->whereDate('asked_at', today());
        } elseif ($this->filterPeriod === 'week') {
            $query->whereBetween('asked_at', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($this->filterPeriod === 'month') {
            $query->whereMonth('asked_at', now()->month)
                  ->whereYear('asked_at', now()->year);
        }

        // Apply weapon filter
        if ($this->filterWeapon) {
            $query->where('weapon_id', $this->filterWeapon);
        }

        // Apply custom date filters
        if ($this->filterFromDate) {
            $query->whereDate('asked_at', '>=', $this->filterFromDate);
        }

        if ($this->filterToDate) {
            $query->whereDate('asked_at', '<=', $this->filterToDate);
        }

        $this->filteredHistory = $query->get();
        $this->updateContextInfo();
    }

    protected function updateContextInfo(): void
    {
        $context = [];
        
        // Weapon context
        if ($this->filterWeapon) {
            $weapon = Weapon::find($this->filterWeapon);
            $context[] = $weapon?->name ?? 'Specifiek wapen';
        } else {
            $context[] = 'Alle wapens';
        }
        
        // Date context
        if ($this->filterPeriod === 'today') {
            $context[] = 'Vandaag';
        } elseif ($this->filterPeriod === 'week') {
            $context[] = 'Deze week';
        } elseif ($this->filterPeriod === 'month') {
            $context[] = 'Deze maand';
        } elseif ($this->filterFromDate && $this->filterToDate) {
            $context[] = Carbon::parse($this->filterFromDate)->format('d-m-Y') . ' → ' . Carbon::parse($this->filterToDate)->format('d-m-Y');
        } elseif ($this->filterFromDate) {
            $context[] = Carbon::parse($this->filterFromDate)->format('d-m-Y') . ' → nu';
        } else {
            $context[] = '01-01-2026 → nu';
        }
        
        $this->contextInfo = implode(' · ', $context);
    }

    public function startNewSession(): void
    {
        $this->currentSessionId = (string) \Illuminate\Support\Str::uuid();
        $this->currentSession = collect();
        $this->loadSessions();
    }

    public function loadSession(string $sessionId): void
    {
        $this->currentSessionId = $sessionId;
        $this->currentSession = CoachQuestion::where('user_id', auth()->id())
            ->where('session_id', $sessionId)
            ->orderBy('asked_at')
            ->get();
        $this->loadSessions();
    }

    protected function loadSessions(): void
    {
        // Load current session (last 20 messages)
        $this->currentSession = CoachQuestion::where('user_id', auth()->id())
            ->when($this->currentSessionId, fn ($query) => $query->where('session_id', $this->currentSessionId))
            ->orderBy('asked_at', 'desc')
            ->limit(20)
            ->get()
            ->reverse();

        // If no current session exists, try to load existing questions without session_id
        if ($this->currentSession->isEmpty()) {
            $this->currentSession = CoachQuestion::where('user_id', auth()->id())
                ->whereNull('session_id')
                ->orderBy('asked_at', 'desc')
                ->limit(20)
                ->get()
                ->reverse();
        }

        // Load old sessions (grouped by session_id)
        $this->oldSessions = CoachQuestion::where('user_id', auth()->id())
            ->whereNotNull('session_id')
            ->when($this->currentSessionId, fn ($query) => $query->where('session_id', '!=', $this->currentSessionId))
            ->selectRaw('session_id, MIN(asked_at) as started_at, COUNT(*) as message_count, MAX(asked_at) as last_activity, MIN(question) as first_question')
            ->groupBy('session_id')
            ->orderBy('last_activity', 'desc')
            ->get()
            ->map(function ($session) {
                $session->started_at = \Carbon\Carbon::parse($session->started_at);
                $session->last_activity = \Carbon\Carbon::parse($session->last_activity);
                return $session;
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CoachSession::query()
                    ->forUser(auth()->id())
                    ->excludeCurrent($this->currentSessionId)
                    ->orderBy('last_activity', 'desc')
            )
            ->columns([
                TextColumn::make('first_question')
                    ->label('Eerste vraag')
                    ->limit(50)
                    ->searchable(),
                
                TextColumn::make('message_count')
                    ->label('Berichten')
                    ->numeric()
                    ->sortable(),
                
                TextColumn::make('started_at')
                    ->label('Gestart')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
                
                TextColumn::make('last_activity')
                    ->label('Laatste activiteit')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->actions([
                FilamentAction::make('load')
                    ->label('Openen')
                    ->icon('heroicon-o-arrow-right')
                    ->action(fn ($record) => $this->loadSession($record->session_id)),
            ])
            ->emptyStateHeading('Geen vorige gesprekken')
            ->emptyStateDescription('Je hebt nog geen eerdere gesprekken.')
            ->emptyStateActions([
                FilamentAction::make('startNew')
                    ->label('Start nieuw gesprek')
                    ->icon('heroicon-o-plus')
                    ->action('startNewSession'),
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([10, 25, 50]);
    }

    public function viewFullAnswer(int $questionId): void
    {
        $question = CoachQuestion::findOrFail($questionId);
        
        // Hier zou je een modal kunnen tonen of de pagina kunnen updaten
        // Voor nu tonen we een notificatie met de volledige vraag
        Notification::make()
            ->title('Volledige vraag')
            ->body($question->question)
            ->info()
            ->send();
    }

    protected function askQuestion(array $state): void
    {
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

        $limit = config('ai.daily_question_limit', 10);
        $decayMinutes = config('ai.daily_question_decay_minutes', 1440);
        $key = 'ai-coach-questions:' . $user->id;

        if (RateLimiter::tooManyAttempts($key, $limit, $decayMinutes)) {
            $availableIn = RateLimiter::availableIn($key);
            Notification::make()
                ->title('Daglimiet bereikt')
                ->body("Je hebt het maximale aantal vragen voor vandaag bereikt. Probeer het over {$availableIn} seconden opnieuw.")
                ->warning()
                ->send();

            return;
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        // Initialiseer AI Coach service
        $coach = app(\App\Services\Ai\ShooterCoach::class);
        $this->answer = $coach->answerCoachQuestion($user, $question, $weaponId, $from, $to);

        CoachQuestion::create([
            'user_id' => $user->id,
            'session_id' => $this->currentSessionId,
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
            ->take(10)
            ->get();
    }

    public function exportCsv(): void
    {
        $records = CoachQuestion::query()
            ->with('weapon')
            ->where('user_id', auth()->id())
            ->latest('asked_at')
            ->get();

        $csv = collect(['Datum', 'Vraag', 'Antwoord', 'Wapen', 'Periode van', 'Periode tot'])
            ->merge($records->map(fn ($r) => [
                $r->asked_at->format('Y-m-d H:i:s'),
                str_replace(["\n", "\r"], ' ', $r->question),
                str_replace(["\n", "\r"], ' ', $r->answer),
                $r->weapon?->name ?? '',
                $r->period_from?->format('Y-m-d') ?? '',
                $r->period_to?->format('Y-m-d') ?? '',
            ]))
            ->map(fn ($row) => implode(';', $row))
            ->implode("\n");

        response()->streamDownload(
            fn () => print ($csv),
            'ai-coach-vragen-'.now()->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv']
        );
    }
}

<?php

namespace App\Livewire;

use App\Models\Session;
use App\Models\SessionShot;
use App\Services\Sessions\SessionShotService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Columns\Summarizers\Average;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;

class SessionShotBoard extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public Session $session;

    public bool $readOnly = false;

    public int $currentTurnIndex = 0;

    /** @var array<int, array<int, array<string, mixed>>> */
    public array $shotsByTurn = [];

    /** @var array<int, array<string, mixed>> */
    public array $markers = [];

    public ?int $selectedTurnId = null;

    /** @var array<string, mixed> */
    public array $summary = [];

    /** @var array<int, int> */
    public array $turnOptions = [0];

    public bool $canEdit = false;

    protected SessionShotService $shotService;

    public function boot(SessionShotService $shotService): void
    {
        $this->shotService = $shotService;
    }

    public function mount(Session $session): void
    {
        $this->session = $session;
        $this->canEdit = Gate::allows('update', $session)
            || ($session->user_id === auth()->id());

        if ($this->readOnly) {
            $this->canEdit = false;
        }

        $this->refreshData();
        $this->currentTurnIndex = $this->turnOptions[0] ?? 0;
        $this->selectedTurnId = $this->currentTurnIndex;
        $this->rebuildShotTable();

        Log::info('[SessionShotBoard] mounted', [
            'session_id' => $session->id,
            'user_id' => auth()->id(),
            'turn_options' => $this->turnOptions,
            'can_edit' => $this->canEdit,
        ]);
    }

    public function setTurn(int $turnIndex): void
    {
        if (! in_array($turnIndex, $this->turnOptions, true)) {
            return;
        }

        $this->currentTurnIndex = $turnIndex;
        $this->selectedTurnId = $turnIndex;
        $this->resetTable();
    }

    public function addTurn(): void
    {
        $next = empty($this->turnOptions)
            ? 0
            : (max($this->turnOptions) + 1);

        $this->turnOptions[] = $next;
        $this->turnOptions = array_values(array_unique($this->turnOptions));
        $this->currentTurnIndex = $next;

        Log::info('[SessionShotBoard] added turn', [
            'session_id' => $this->session->id,
            'turn_index' => $next,
            'turn_options' => $this->turnOptions,
        ]);
    }

    public function recordShot(float $xNormalized, float $yNormalized): void
    {
        if (! $this->canEdit) {
            return;
        }

        Log::info('[SessionShotBoard] recordShot triggered', [
            'session_id' => $this->session->id,
            'turn_index' => $this->currentTurnIndex,
            'x' => $xNormalized,
            'y' => $yNormalized,
        ]);

        $this->shotService->recordShot(
            $this->session,
            $this->currentTurnIndex,
            $xNormalized,
            $yNormalized,
        );

        $this->refreshData();
        $this->resetTable();
    }

    public function deleteShot(int $shotId): void
    {
        if (! $this->canEdit) {
            return;
        }

        $shot = $this->session->shots()->whereKey($shotId)->first();

        if (! $shot instanceof SessionShot) {
            return;
        }

        $this->shotService->deleteShot($shot);
        $this->refreshData();
        $this->resetTable();
    }

    #[On('shots::refresh')]
    public function refreshData(): void
    {
        $this->session->refresh()->load('shots');

        $grouped = $this->session->shots
            ->sortBy([
                ['turn_index', 'asc'],
                ['shot_index', 'asc'],
            ])
            ->groupBy('turn_index');

        $this->shotsByTurn = $grouped
            ->map(fn (Collection $shots) => $shots->map(fn (SessionShot $shot) => [
                'id' => $shot->id,
                'turn_index' => $shot->turn_index,
                'shot_index' => $shot->shot_index,
                'score' => $shot->score,
                'ring' => $shot->ring,
                'x_normalized' => $shot->x_normalized,
                'y_normalized' => $shot->y_normalized,
                'distance_from_center' => $shot->distance_from_center,
                'created_at' => $shot->created_at?->format('H:i'),
            ])->values()->all())
            ->toArray();

        $this->markers = $this->session->shots
            ->map(fn (SessionShot $shot) => [
                'id' => $shot->id,
                'x' => $shot->x_normalized,
                'y' => $shot->y_normalized,
                'score' => $shot->score,
                'ring' => $shot->ring,
                'turn_index' => $shot->turn_index,
            ])
            ->values()
            ->all();

        $summary = $this->shotService->summarize($this->session);

        $this->summary = array_merge(
            [
                'total_score' => 0,
                'shot_count' => 0,
                'average_score' => 0,
                'turns' => [],
            ],
            $summary,
        );

        $dbTurns = $grouped->keys()
            ->map(fn ($key) => (int) $key)
            ->unique()
            ->values();

        $this->turnOptions = collect($this->turnOptions)
            ->merge($dbTurns)
            ->unique()
            ->sort()
            ->values()
            ->all();

        foreach ($this->turnOptions as $turn) {
            $this->shotsByTurn[$turn] ??= [];
        }

        ksort($this->shotsByTurn);

        if (empty($this->turnOptions)) {
            $this->turnOptions = [0];
        }

        if (! in_array($this->currentTurnIndex, $this->turnOptions, true)) {
            $this->currentTurnIndex = $this->turnOptions[0];
        }

        Log::debug('[SessionShotBoard] data refreshed', [
            'session_id' => $this->session->id,
            'turn_options' => $this->turnOptions,
            'current_turn' => $this->currentTurnIndex,
            'markers_count' => count($this->markers),
        ]);

        $this->resetTable();
    }

    protected function getTurnFilterOptions(): array
    {
        $options = [
            'current' => 'Huidige beurt',
            'all' => 'Alle beurten',
        ];

        foreach ($this->turnOptions as $turnIndex) {
            $options[(string) $turnIndex] = 'Beurt '.($turnIndex + 1);
        }

        return $options;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SessionShot::query()
                    ->where('session_id', $this->session->id)
            )
            ->paginated(15)
            ->paginationPageOptions([10, 15, 25, 50])
            ->defaultSort('turn_index')
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('turn_index')
                    ->label('Beurt')
                    ->formatStateUsing(fn (?int $state) => 'Beurt '.(($state ?? 0) + 1))
                    ->sortable(),
                TextColumn::make('shot_index')
                    ->label('Schot')
                    ->formatStateUsing(fn (?int $state) => 'Schot '.(($state ?? 0) + 1))
                    ->summarize([
                        Count::make()->label('Schoten'),
                    ]),
                TextColumn::make('ring')
                    ->label('Ring')
                    ->badge()
                    ->color(fn (?int $state) => $state >= 9 ? 'success' : ($state >= 7 ? 'primary' : 'gray'))
                    ->sortable(),
                TextColumn::make('score')
                    ->label('Score')
                    ->formatStateUsing(fn (?int $state) => $state ? '+'.$state : '+0')
                    ->summarize([
                        Sum::make()->label('Totaal'),
                        Average::make()->label('Gemiddelde')->formatStateUsing(fn ($state) => number_format((float) $state, 2)),
                    ]),
                TextColumn::make('created_at')
                    ->label('Tijd')
                    ->dateTime('H:i')
                    ->default('—'),
            ])
            ->filters([
                SelectFilter::make('turn_scope')
                    ->label('Beurt')
                    ->options(fn () => $this->getTurnFilterOptions())
                    ->default('current')
                    ->query(function (Builder $query, $value) {
                        if ($value === 'all') {
                            return;
                        }

                        if ($value === 'current') {
                            $query->where('turn_index', $this->currentTurnIndex);

                            return;
                        }

                        if (is_numeric($value)) {
                            $query->where('turn_index', (int) $value);
                        }
                    }),
            ])
            ->actions([
                Action::make('deleteShot')
                    ->label('Verwijderen')
                    ->color('danger')
                    ->icon('heroicon-m-trash')
                    ->visible(fn () => $this->canEdit)
                    ->requiresConfirmation()
                    ->action(fn (SessionShot $record) => $this->deleteShot($record->id)),
            ])
            ->emptyStateHeading('Nog geen schoten geregistreerd')
            ->recordUrl(null);
    }

    public function render()
    {
        return view('livewire.session-shot-board');
    }

    protected function rebuildShotTable(): void
    {
        // No-op: the Filament table queries data directly from the database.
        // Method kept to satisfy legacy calls made before the Filament table refactor.
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }
}

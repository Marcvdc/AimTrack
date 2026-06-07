<?php

namespace App\Filament\Resources;

use App\Enums\Deviation;
use App\Filament\Copilot\Tools\SessionLookupTool;
use App\Filament\Resources\SessionResource\Pages\CreateSession;
use App\Filament\Resources\SessionResource\Pages\EditSession;
use App\Filament\Resources\SessionResource\Pages\ListSessions;
use App\Filament\Resources\SessionResource\Pages\ManageSessionShots;
use App\Filament\Resources\SessionResource\Pages\ViewSession;
use App\Jobs\GenerateSessionReflectionJob;
use App\Models\AmmoType;
use App\Models\Location;
use App\Models\Session;
use App\Support\Features\AimtrackFeatureToggle;
use EslamRedaDiv\FilamentCopilot\Contracts\CopilotResource as CopilotResourceContract;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section as InfoSection;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class SessionResource extends Resource implements CopilotResourceContract
{
    protected static ?string $model = Session::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Sessies';

    protected static ?string $modelLabel = 'Sessie';

    protected static ?string $pluralModelLabel = 'Sessies';

    protected static string|\UnitEnum|null $navigationGroup = 'LOG';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                static::userIdField(),

                InfoSection::make('Sessie')
                    ->description('Basisgegevens van de sessie')
                    ->columns(2)
                    ->schema([
                        ...static::sessionDetailFields(),
                        ...static::notesFields(),
                    ]),

                InfoSection::make('Wapens in deze sessie')
                    ->description('Voeg per wapen de afstand en schoten toe')
                    ->schema([
                        static::sessionWeaponsRepeater(),
                    ]),

                InfoSection::make('Bijlagen')
                    ->description('Upload foto’s of PDF’s als context')
                    ->schema([
                        static::attachmentsRepeater(),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function userIdField(): Hidden
    {
        return Hidden::make('user_id')
            ->default(fn () => auth()->id())
            ->required()
            ->dehydrated(fn ($state) => filled($state));
    }

    /**
     * Basisvelden van de sessie (zonder notities), herbruikt door de
     * Edit-form en de Range Console nieuwe-sessie wizard (stap Sessie).
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function sessionDetailFields(): array
    {
        return [
            DatePicker::make('date')
                ->label('Datum')
                ->native(false)
                ->required(),
            Select::make('target_type')
                ->label('Discipline')
                ->options([
                    'kkp_25m' => 'KKP 25m (klein kaliber pistool)',
                    'gkp_25m' => 'GKP 25m (groot kaliber pistool)',
                    'kkg_50m' => 'KKG 50m (klein kaliber geweer)',
                    'kkg_100m' => 'KKG 100m (klein kaliber geweer)',
                    'gkg_100m' => 'GKG 100m (groot kaliber geweer)',
                ])
                ->helperText('Vereist om foto-analyse te kunnen uitvoeren.')
                ->native(false),
            Select::make('range_location_id')
                ->label('Baan/vereniging')
                ->options(fn () => Location::query()
                    ->where('user_id', auth()->id())
                    ->where('is_range', true)
                    ->orderBy('name')
                    ->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->nullable()
                ->afterStateUpdated(function ($state, callable $set): void {
                    if (! $state) {
                        return;
                    }

                    $location = Location::query()->find($state);

                    if ($location) {
                        $set('range_name', $location->name);
                    }
                }),
            Select::make('location_id')
                ->label('Locatie')
                ->options(fn () => Location::query()
                    ->where('user_id', auth()->id())
                    ->orderBy('name')
                    ->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->nullable()
                ->afterStateUpdated(function ($state, callable $set): void {
                    if (! $state) {
                        return;
                    }

                    $location = Location::query()->find($state);

                    if ($location) {
                        $set('location', $location->name);
                    }
                }),
            Hidden::make('range_name')
                ->dehydrated(),
            Hidden::make('location')
                ->dehydrated(),
        ];
    }

    /**
     * Notitie-velden, herbruikt door de Edit-form en de wizard (stap Notities).
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function notesFields(): array
    {
        return [
            Textarea::make('notes_raw')
                ->label('Notities (ruw)')
                ->rows(4)
                ->columnSpanFull(),
            Textarea::make('manual_reflection')
                ->label('Handmatige reflectie')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }

    public static function sessionWeaponsRepeater(): Repeater
    {
        return Repeater::make('sessionWeapons')
            ->label('Sessiewapens')
            ->relationship()
            ->schema([
                Select::make('weapon_id')
                    ->label('Wapen')
                    ->relationship(
                        name: 'weapon',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->where('user_id', auth()->id()),
                    )
                    ->required()
                    ->preload(),
                TextInput::make('distance_m')
                    ->label('Afstand (m)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(2000),
                TextInput::make('rounds_fired')
                    ->label('Afgevuurde patronen')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->default(0),
                Hidden::make('ammo_type')
                    ->dehydrated(),
                Select::make('ammo_type_id')
                    ->label('Munitietype')
                    ->options(fn () => AmmoType::query()
                        ->where('user_id', auth()->id())
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->afterStateUpdated(function ($state, callable $set): void {
                        if (! $state) {
                            return;
                        }

                        $ammoType = AmmoType::query()->find($state);

                        if ($ammoType) {
                            $set('ammo_type', $ammoType->name);
                        }
                    }),
                Textarea::make('group_quality_text')
                    ->label('Groepering / kwaliteit')
                    ->rows(2)
                    ->columnSpanFull(),
                Select::make('deviation')
                    ->label('Afwijking')
                    ->options(
                        collect(Deviation::cases())
                            ->mapWithKeys(fn (Deviation $case) => [$case->value => ucfirst($case->value)])
                            ->all(),
                    )
                    ->native(false),
                TextInput::make('flyers_count')
                    ->label('Flyers (aantal)')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
            ])
            ->columns(2)
            ->orderable(false)
            ->addActionLabel('Regel toevoegen')
            ->collapsed(false);
    }

    public static function attachmentsRepeater(): Repeater
    {
        return Repeater::make('attachments')
            ->label('Bijlagen')
            ->relationship()
            ->schema([
                FileUpload::make('path')
                    ->label('Bestand')
                    ->required()
                    ->maxSize(20480)
                    ->directory('attachments')
                    ->preserveFilenames()
                    ->downloadable()
                    ->openable()
                    ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => $file->getClientOriginalName())
                    ->afterStateUpdated(function ($state, callable $set): void {
                        if (! $state) {
                            return;
                        }

                        $file = is_array($state) ? end($state) : $state;

                        if (! $file instanceof TemporaryUploadedFile) {
                            return;
                        }

                        $set('original_name', $file->getClientOriginalName());
                        $set('mime_type', $file->getMimeType());
                        $set('size', $file->getSize());
                    }),
                Hidden::make('original_name')
                    ->dehydrated()
                    ->required(),
                Hidden::make('mime_type')
                    ->dehydrated()
                    ->required(),
                Hidden::make('size')
                    ->dehydrated()
                    ->required(),
            ])
            ->columns(2)
            ->orderable(false)
            ->itemLabel(fn (array $state): string => $state['original_name'] ?? 'Bijlage')
            ->addActionLabel('Bijlage toevoegen');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('sessionWeapons.weapon')
                ->withSum('shots as total_score', 'score')
                ->withExists('aiReflection'))
            ->columns([
                TextColumn::make('date')
                    ->label('Datum')
                    ->date()
                    ->sortable(),
                TextColumn::make('range_name')
                    ->label('Baan/vereniging')
                    ->searchable(),
                TextColumn::make('location')
                    ->label('Locatie')
                    ->searchable(),
                TextColumn::make('sessionWeaponsSummary')
                    ->label('Wapens')
                    ->state(fn (Session $record) => $record->sessionWeapons
                        ->pluck('weapon.name')
                        ->filter()
                        ->unique()
                        ->values()
                        ->join(', '))
                    ->badge()
                    ->toggleable(),
                TextColumn::make('totalRounds')
                    ->label('Schoten')
                    ->state(fn (Session $record) => $record->sessionWeapons->sum('rounds_fired'))
                    ->sortable(),
                TextColumn::make('totalScore')
                    ->label('Score')
                    ->state(fn (Session $record): int => (int) ($record->total_score ?? 0))
                    ->color(fn ($state): string => ((int) $state) > 0 ? 'success' : 'gray')
                    ->weight('semibold'),
                TextColumn::make('reflectionStatus')
                    ->label('Status')
                    ->state(fn (Session $record): string => $record->ai_reflection_exists ? 'AI' : 'open')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'AI' ? 'success' : 'warning'),
                TextColumn::make('aiReflection.summary')
                    ->label('AI-reflectie')
                    ->icon('heroicon-m-sparkles')
                    ->limit(40)
                    ->placeholder('Nog niet gegenereerd')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible(fn (): bool => static::features()->aiEnabled()),
            ])
            ->filters([
                Filter::make('periode')
                    ->label('Periode')
                    ->schema([
                        DatePicker::make('from')->label('Vanaf'),
                        DatePicker::make('until')->label('Tot'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('date', '<=', $date));
                    }),
                SelectFilter::make('weapon')
                    ->label('Wapen')
                    ->relationship('sessionWeapons.weapon', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('generateAiReflection')
                    ->label('Genereer AI-reflectie nu')
                    ->tooltip('BETA')
                    ->icon('heroicon-m-sparkles')
                    ->requiresConfirmation()
                    ->action(function (Session $record): void {
                        if (static::features()->aiDisabled()) {
                            Notification::make()
                                ->title('AI-functies uitgeschakeld')
                                ->body('Schakel de featureflag aimtrack-ai in om AI-reflecties te genereren.')
                                ->warning()
                                ->send();

                            return;
                        }

                        GenerateSessionReflectionJob::dispatch($record);

                        Notification::make()
                            ->title('AI-reflectie ingepland')
                            ->body('De job is toegevoegd aan de wachtrij.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyState(view('filament.resources.sessions.empty-state'));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('Details en reflectie')
                    ->contained(false)
                    ->tabs([
                        Tab::make('Details')
                            ->columns(1)
                            ->schema([
                                InfoSection::make('Sessie')
                                    ->contained(false)
                                    ->description('Eigen invoer')
                                    ->schema([
                                        TextEntry::make('date')->label('Datum')->date(),
                                        TextEntry::make('range_name')->label('Baan/vereniging'),
                                        TextEntry::make('location')->label('Locatie'),
                                        TextEntry::make('notes_raw')
                                            ->label('Notities (ruw)')
                                            ->markdown(),
                                        TextEntry::make('manual_reflection')
                                            ->label('Handmatige reflectie (gebruiker)')
                                            ->markdown(),
                                    ]),
                                InfoSection::make('Sessiewapens')
                                    ->contained(false)
                                    ->schema([
                                        RepeatableEntry::make('sessionWeapons')
                                            ->schema([
                                                TextEntry::make('weapon.name')->label('Wapen'),
                                                TextEntry::make('distance_m')->label('Afstand (m)'),
                                                TextEntry::make('rounds_fired')->label('Patronen'),
                                                TextEntry::make('ammo_type')->label('Munitie'),
                                                TextEntry::make('group_quality_text')->label('Groepering'),
                                                TextEntry::make('deviation')->label('Afwijking'),
                                                TextEntry::make('flyers_count')->label('Flyers'),
                                            ])
                                            ->columns(3),
                                    ]),
                                InfoSection::make('Bijlagen')
                                    ->contained(false)
                                    ->schema([
                                        RepeatableEntry::make('attachments')
                                            ->schema([
                                                TextEntry::make('original_name')->label('Bestand'),
                                                TextEntry::make('mime_type')->label('MIME'),
                                                TextEntry::make('size')->label('Grootte (bytes)'),
                                            ])
                                            ->visible(fn (Session $record) => $record->attachments()->exists())
                                            ->columns(3),
                                    ]),
                            ]),
                        Tab::make('Schoten')
                            ->columns(1)
                            ->schema([
                                InfoSection::make('Interactieve roos & schoten per beurt')
                                    ->contained(false)
                                    ->description('Leg schoten vast per beurt en zie direct de totals. Beschikbaar tijdens het bewerken.')
                                    ->schema([
                                        ViewComponent::make('filament.sessions.session-shot-board-panel')
                                            ->viewData(fn (?Session $record = null) => [
                                                'readOnly' => true,
                                                'record' => $record,
                                            ])
                                            ->key(fn (?Session $record) => 'session-shot-board-form-'.($record?->getKey() ?? 'new')),
                                    ]),
                            ]),
                        Tab::make('AI-reflectie')
                            ->visible(fn (): bool => static::features()->aiEnabled())
                            ->columns(1)
                            ->schema([
                                InfoSection::make('Reflectie door AI')
                                    ->contained(false)
                                    ->description('Automatisch gegenereerd; gebruik ter inspiratie, blijf kritisch en veilig schieten.')
                                    ->schema([
                                        TextEntry::make('aiReflection.summary')
                                            ->label('Samenvatting')
                                            ->icon('heroicon-o-sparkles')
                                            ->placeholder('Nog niet beschikbaar'),
                                        TextEntry::make('aiReflection.positives')
                                            ->label('Wat ging goed')
                                            ->bulleted()
                                            ->icon('heroicon-o-sparkles')
                                            ->placeholder('Nog niet beschikbaar'),
                                        TextEntry::make('aiReflection.improvements')
                                            ->label('Verbeterpunten')
                                            ->bulleted()
                                            ->icon('heroicon-o-sparkles')
                                            ->placeholder('Nog niet beschikbaar'),
                                        TextEntry::make('aiReflection.next_focus')
                                            ->label('Focus voor volgende keer')
                                            ->icon('heroicon-o-sparkles')
                                            ->placeholder('Nog niet beschikbaar'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSessions::route('/'),
            'create' => CreateSession::route('/create'),
            'edit' => EditSession::route('/{record}/edit'),
            'view' => ViewSession::route('/{record}'),
            'shots' => ManageSessionShots::route('/{record}/shots'),
        ];
    }

    protected static function features(): AimtrackFeatureToggle
    {
        return app(AimtrackFeatureToggle::class);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }

    public static function copilotResourceDescription(): ?string
    {
        return 'Schiet-sessies van de schutter, inclusief wapenregels, schoten en eventuele AI-reflectie. Filter altijd op de eigenaar.';
    }

    public static function copilotTools(): array
    {
        return [
            new SessionLookupTool,
        ];
    }
}

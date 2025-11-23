<?php

namespace App\Filament\Resources;

use App\Enums\Deviation;
use App\Jobs\GenerateSessionReflectionJob;
use App\Models\Session;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class SessionResource extends Resource
{
    protected static ?string $model = Session::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Sessies';

    protected static ?string $modelLabel = 'Sessie';

    protected static ?string $pluralModelLabel = 'Sessies';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Hidden::make('user_id')
                    ->default(fn () => auth()->id())
                    ->required()
                    ->dehydrated(fn ($state) => filled($state)),

                Section::make('Sessie')
                    ->description('Basisgegevens van de sessie')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('date')
                            ->label('Datum')
                            ->native(false)
                            ->required(),
                        TextInput::make('range_name')
                            ->label('Baan/vereniging')
                            ->maxLength(255),
                        TextInput::make('location')
                            ->label('Locatie')
                            ->maxLength(255),
                        Textarea::make('notes_raw')
                            ->label('Notities (ruw)')
                            ->rows(4)
                            ->columnSpanFull(),
                        Textarea::make('manual_reflection')
                            ->label('Handmatige reflectie')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Wapens in deze sessie')
                    ->description('Voeg per wapen de afstand en schoten toe')
                    ->schema([
                        Repeater::make('sessionWeapons')
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
                                TextInput::make('ammo_type')
                                    ->label('Munitie type')
                                    ->maxLength(255),
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
                            ->collapsed(false),
                        // Eventueel uitbreiden met munitie-voorraad check of scorevelden.
                    ]),

                Section::make('Bijlagen')
                    ->description('Upload foto’s of PDF’s als context')
                    ->schema([
                        Repeater::make('attachments')
                            ->label('Bijlagen')
                            ->relationship()
                            ->schema([
                                FileUpload::make('path')
                                    ->label('Bestand')
                                    ->required()
                                    ->directory('attachments')
                                    ->preserveFilenames()
                                    ->downloadable()
                                    ->openable()
                                    ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => $file->getClientOriginalName())
                                    ->afterStateUpdated(function ($state, callable $set, ?TemporaryUploadedFile $file): void {
                                        if (! $file) {
                                            return;
                                        }

                                        $set('original_name', $file->getClientOriginalName());
                                        $set('mime_type', $file->getMimeType());
                                        $set('size', $file->getSize());
                                    }),
                                TextInput::make('original_name')
                                    ->label('Bestandsnaam')
                                    ->required(),
                                TextInput::make('mime_type')
                                    ->label('MIME-type')
                                    ->required(),
                                TextInput::make('size')
                                    ->label('Grootte (bytes)')
                                    ->numeric()
                                    ->required(),
                            ])
                            ->columns(2)
                            ->orderable(false)
                            ->itemLabel(fn (array $state): string => $state['original_name'] ?? 'Bijlage')
                            ->addActionLabel('Bijlage toevoegen'),
                        // Extra tuning: validatie op toegestane mime-types of max grootte.
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                    ->label('Aantal schoten')
                    ->state(fn (Session $record) => $record->sessionWeapons->sum('rounds_fired'))
                    ->sortable(),
                TextColumn::make('aiReflection.summary')
                    ->label('AI-reflectie')
                    ->limit(40)
                    ->placeholder('Nog niet gegenereerd')
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('periode')
                    ->label('Periode')
                    ->form([
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
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Action::make('generateAiReflection')
                    ->label('Genereer AI-reflectie nu')
                    ->icon('heroicon-m-sparkles')
                    ->requiresConfirmation()
                    ->action(function (Session $record): void {
                        GenerateSessionReflectionJob::dispatch($record);

                        Notification::make()
                            ->title('AI-reflectie ingepland')
                            ->body('De job is toegevoegd aan de wachtrij.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Tabs::make('Details en reflectie')
                    ->tabs([
                        Tab::make('Details')
                            ->schema([
                                InfoSection::make('Sessie')
                                    ->schema([
                                        TextEntry::make('date')->label('Datum')->date(),
                                        TextEntry::make('range_name')->label('Baan/vereniging'),
                                        TextEntry::make('location')->label('Locatie'),
                                        TextEntry::make('notes_raw')->label('Notities (ruw)')->markdown(),
                                        TextEntry::make('manual_reflection')->label('Handmatige reflectie')->markdown(),
                                    ]),
                                InfoSection::make('Sessiewapens')
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
                        Tab::make('AI-reflectie')
                            ->schema([
                                InfoSection::make('Reflectie door AI')
                                    ->schema([
                                        TextEntry::make('aiReflection.summary')
                                            ->label('Samenvatting')
                                            ->placeholder('Nog niet beschikbaar'),
                                        TextEntry::make('aiReflection.positives')
                                            ->label('Wat ging goed')
                                            ->bulleted()
                                            ->placeholder('Nog niet beschikbaar'),
                                        TextEntry::make('aiReflection.improvements')
                                            ->label('Verbeterpunten')
                                            ->bulleted()
                                            ->placeholder('Nog niet beschikbaar'),
                                        TextEntry::make('aiReflection.next_focus')
                                            ->label('Focus voor volgende keer')
                                            ->placeholder('Nog niet beschikbaar'),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => SessionResource\Pages\ListSessions::route('/'),
            'create' => SessionResource\Pages\CreateSession::route('/create'),
            'edit' => SessionResource\Pages\EditSession::route('/{record}/edit'),
            'view' => SessionResource\Pages\ViewSession::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }
}

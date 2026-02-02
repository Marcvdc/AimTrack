<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WeaponResource\RelationManagers\SessionWeaponsRelationManager;
use App\Filament\Resources\WeaponResource\Pages\ListWeapons;
use App\Filament\Resources\WeaponResource\Pages\CreateWeapon;
use App\Filament\Resources\WeaponResource\Pages\EditWeapon;
use App\Filament\Resources\WeaponResource\Pages\ViewWeapon;
use App\Enums\WeaponType;
use App\Jobs\GenerateWeaponInsightJob;
use App\Models\AmmoType;
use App\Models\Location;
use App\Models\Weapon;
use App\Support\Features\AimtrackFeatureToggle;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section as InfoSection;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class WeaponResource extends Resource
{
    protected static ?string $model = Weapon::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationLabel = 'Wapens';

    protected static ?string $modelLabel = 'Wapen';

    protected static ?string $pluralModelLabel = 'Wapens';

    protected static string | \UnitEnum | null $navigationGroup = 'Beheer';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('user_id')
                    ->default(fn () => auth()->id())
                    ->required()
                    ->dehydrated(fn ($state) => filled($state)),

                InfoSection::make('Basisgegevens')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Naam')
                            ->required()
                            ->maxLength(255),
                        Select::make('weapon_type')
                            ->label('Type wapen')
                            ->options(
                                collect(WeaponType::cases())
                                    ->mapWithKeys(fn (WeaponType $type) => [$type->value => ucfirst($type->value)])
                                    ->all(),
                            )
                            ->required()
                            ->native(false),
                        Select::make('caliber')
                            ->label('Kaliber')
                            ->options(fn () => AmmoType::query()
                                ->where('user_id', auth()->id())
                                ->whereNotNull('caliber')
                                ->orderBy('caliber')
                                ->distinct()
                                ->pluck('caliber', 'caliber'))
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('serial_number')
                            ->label('Serienummer')
                            ->maxLength(255),
                        TextInput::make('storage_location')
                            ->label('Opslaglocatie (tekst)')
                            ->helperText('Vrij tekstveld; gebruik bij voorkeur de referentie-selectie hieronder.')
                            ->maxLength(255),
                        Select::make('storage_location_id')
                            ->label('Opslaglocatie')
                            ->options(fn () => Location::query()
                                ->where('user_id', auth()->id())
                                ->where('is_storage', true)
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
                                    $set('storage_location', $location->name);
                                }
                            }),
                        DatePicker::make('owned_since')
                            ->label('In bezit sinds')
                            ->native(false),
                        Toggle::make('is_active')
                            ->label('Actief')
                            ->inline(false)
                            ->default(true),
                        Textarea::make('notes')
                            ->label('Notities')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('weapon_type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('caliber')
                    ->label('Kaliber')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean(),
                TextColumn::make('sessionWeapons_count')
                    ->label('Sessies')
                    ->counts('sessionWeapons'),
                TextColumn::make('aiWeaponInsight.summary')
                    ->label('AI-inzichten')
                    ->limit(40)
                    ->placeholder('Nog niet gegenereerd')
                    ->visible(fn (): bool => static::features()->aiEnabled()),
            ])
            ->filters([
                SelectFilter::make('weapon_type')
                    ->label('Type')
                    ->options(
                        collect(WeaponType::cases())
                            ->mapWithKeys(fn (WeaponType $type) => [$type->value => ucfirst($type->value)])
                            ->all(),
                    ),
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        1 => 'Actief',
                        0 => 'Uit gebruik',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('generateAiWeaponInsight')
                    ->label('Genereer AI-inzichten nu')
                    ->icon('heroicon-m-sparkles')
                    ->requiresConfirmation()
                    ->action(function (Weapon $record): void {
                        if (static::features()->aiDisabled()) {
                            Notification::make()
                                ->title('AI-functies uitgeschakeld')
                                ->body('Schakel de featureflag aimtrack-ai in om AI-inzichten te genereren.')
                                ->warning()
                                ->send();

                            return;
                        }

                        GenerateWeaponInsightJob::dispatch($record);

                        Notification::make()
                            ->title('AI-inzichten ingepland')
                            ->body('De job is toegevoegd aan de wachtrij.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                InfoSection::make('Wapen')
                    ->schema([
                        TextEntry::make('name')->label('Naam'),
                        TextEntry::make('weapon_type')->label('Type'),
                        TextEntry::make('caliber')->label('Kaliber'),
                        TextEntry::make('serial_number')->label('Serienummer'),
                        TextEntry::make('storageLocation.name')
                            ->label('Opslaglocatie')
                            ->placeholder(fn (Weapon $record): string => $record->storage_location ?? 'Niet ingesteld'),
                        TextEntry::make('owned_since')->label('In bezit sinds')->date(),
                        TextEntry::make('is_active')
                            ->label('Actief')
                            ->formatStateUsing(fn ($state) => $state ? 'Ja' : 'Nee'),
                        TextEntry::make('notes')->label('Notities')->markdown(),
                    ]),
                InfoSection::make('AI-inzichten')
                    ->description('Automatisch gegenereerd; vat trends samen op basis van je sessies.')
                    ->visible(fn (): bool => static::features()->aiEnabled())
                    ->schema([
                        TextEntry::make('aiWeaponInsight.summary')
                            ->label('Samenvatting')
                            ->icon('heroicon-o-sparkles')
                            ->placeholder('Nog niet beschikbaar'),
                        TextEntry::make('aiWeaponInsight.patterns')
                            ->label('Patronen')
                            ->bulleted()
                            ->icon('heroicon-o-sparkles')
                            ->placeholder('Nog niet beschikbaar'),
                        TextEntry::make('aiWeaponInsight.suggestions')
                            ->label('Suggesties')
                            ->bulleted()
                            ->icon('heroicon-o-sparkles')
                            ->placeholder('Nog niet beschikbaar'),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SessionWeaponsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWeapons::route('/'),
            'create' => CreateWeapon::route('/create'),
            'edit' => EditWeapon::route('/{record}/edit'),
            'view' => ViewWeapon::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }

    protected static function features(): AimtrackFeatureToggle
    {
        return app(AimtrackFeatureToggle::class);
    }
}

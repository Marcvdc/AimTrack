<?php

namespace App\Filament\Resources;

use App\Enums\WeaponType;
use App\Jobs\GenerateWeaponInsightJob;
use App\Models\Weapon;
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

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationLabel = 'Wapens';

    protected static ?string $modelLabel = 'Wapen';

    protected static ?string $pluralModelLabel = 'Wapens';

    protected static UnitEnum|string|null $navigationGroup = 'Beheer';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
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
                        TextInput::make('caliber')
                            ->label('Kaliber')
                            ->required()
                            ->maxLength(50),
                        TextInput::make('serial_number')
                            ->label('Serienummer')
                            ->maxLength(255),
                        TextInput::make('storage_location')
                            ->label('Opslaglocatie')
                            ->maxLength(255),
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
                    ->placeholder('Nog niet gegenereerd'),
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
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('generateAiWeaponInsight')
                    ->label('Genereer AI-inzichten nu')
                    ->icon('heroicon-m-sparkles')
                    ->requiresConfirmation()
                    ->action(function (Weapon $record): void {
                        GenerateWeaponInsightJob::dispatch($record);

                        Notification::make()
                            ->title('AI-inzichten ingepland')
                            ->body('De job is toegevoegd aan de wachtrij.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                InfoSection::make('Wapen')
                    ->schema([
                        TextEntry::make('name')->label('Naam'),
                        TextEntry::make('weapon_type')->label('Type'),
                        TextEntry::make('caliber')->label('Kaliber'),
                        TextEntry::make('serial_number')->label('Serienummer'),
                        TextEntry::make('storage_location')->label('Opslaglocatie'),
                        TextEntry::make('owned_since')->label('In bezit sinds')->date(),
                        TextEntry::make('is_active')
                            ->label('Actief')
                            ->formatStateUsing(fn ($state) => $state ? 'Ja' : 'Nee'),
                        TextEntry::make('notes')->label('Notities')->markdown(),
                    ]),
                InfoSection::make('AI-inzichten')
                    ->description('Automatisch gegenereerd; vat trends samen op basis van je sessies.')
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
            WeaponResource\RelationManagers\SessionWeaponsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => WeaponResource\Pages\ListWeapons::route('/'),
            'create' => WeaponResource\Pages\CreateWeapon::route('/create'),
            'edit' => WeaponResource\Pages\EditWeapon::route('/{record}/edit'),
            'view' => WeaponResource\Pages\ViewWeapon::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }
}

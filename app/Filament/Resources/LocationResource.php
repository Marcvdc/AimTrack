<?php

namespace App\Filament\Resources;

use App\Models\Location;
use BackedEnum;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section as InfoSection;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Locaties';

    protected static ?string $modelLabel = 'Locatie';

    protected static ?string $pluralModelLabel = 'Locaties';

    protected static UnitEnum|string|null $navigationGroup = 'Beheer';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Hidden::make('user_id')
                    ->default(fn () => auth()->id())
                    ->required()
                    ->dehydrated(fn ($state) => filled($state)),

                InfoSection::make('Locatie')
                    ->schema([
                        TextInput::make('name')
                            ->label('Naam')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_storage')
                            ->label('Opslaglocatie')
                            ->inline(false),
                        Toggle::make('is_range')
                            ->label('Baan / vereniging')
                            ->inline(false),
                        Textarea::make('notes')
                            ->label('Notities')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
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
                IconColumn::make('is_storage')
                    ->label('Opslaglocatie')
                    ->boolean(),
                IconColumn::make('is_range')
                    ->label('Baan / vereniging')
                    ->boolean(),
            ])
            ->filters([
                Filter::make('storage')
                    ->label('Opslaglocaties')
                    ->query(fn (Builder $query) => $query->where('is_storage', true)),
                Filter::make('ranges')
                    ->label('Banen & Verenigingen')
                    ->query(fn (Builder $query) => $query->where('is_range', true)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => LocationResource\Pages\ListLocations::route('/'),
            'create' => LocationResource\Pages\CreateLocation::route('/create'),
            'edit' => LocationResource\Pages\EditLocation::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }
}

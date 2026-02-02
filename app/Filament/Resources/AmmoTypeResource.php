<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AmmoTypeResource\Pages\ListAmmoTypes;
use App\Filament\Resources\AmmoTypeResource\Pages\CreateAmmoType;
use App\Filament\Resources\AmmoTypeResource\Pages\EditAmmoType;
use App\Models\AmmoType;
use BackedEnum;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section as InfoSection;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AmmoTypeResource extends Resource
{
    protected static ?string $model = AmmoType::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Munitietypen';

    protected static ?string $modelLabel = 'Munitietype';

    protected static ?string $pluralModelLabel = 'Munitietypen';

    protected static string | \UnitEnum | null $navigationGroup = 'Beheer';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('user_id')
                    ->default(fn () => auth()->id())
                    ->required()
                    ->dehydrated(fn ($state) => filled($state)),

                InfoSection::make('Munitietype')
                    ->schema([
                        TextInput::make('name')
                            ->label('Naam')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('caliber')
                            ->label('Kaliber')
                            ->maxLength(50),
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
                TextColumn::make('caliber')
                    ->label('Kaliber')
                    ->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAmmoTypes::route('/'),
            'create' => CreateAmmoType::route('/create'),
            'edit' => EditAmmoType::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id());
    }
}

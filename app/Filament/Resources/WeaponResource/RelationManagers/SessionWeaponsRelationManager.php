<?php

namespace App\Filament\Resources\WeaponResource\RelationManagers;

use App\Enums\Deviation;
use App\Models\AmmoType;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SessionWeaponsRelationManager extends RelationManager
{
    protected static string $relationship = 'sessionWeapons';

    protected static ?string $title = 'Sessies';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('session_id')
                    ->label('Sessie')
                    ->relationship(
                        name: 'session',
                        titleAttribute: 'date',
                        modifyQueryUsing: fn (Builder $query) => $query->where('user_id', auth()->id()),
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record) => sprintf(
                        '%s - %s (%s)',
                        optional($record->date)->format('Y-m-d'),
                        $record->range_name ?? 'onbekend',
                        $record->location ?? 'locatie n.v.t.',
                    ))
                    ->required()
                    ->searchable(),
                TextInput::make('distance_m')
                    ->label('Afstand (m)')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('rounds_fired')
                    ->label('Patronen')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                TextInput::make('ammo_type')
                    ->label('Munitie (tekst)')
                    ->maxLength(255),
                Select::make('ammo_type_id')
                    ->label('Munitietype')
                    ->options(fn () => AmmoType::query()
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

                        $ammoType = AmmoType::query()->find($state);

                        if ($ammoType) {
                            $set('ammo_type', $ammoType->name);
                        }
                    }),
                Textarea::make('group_quality_text')
                    ->label('Groepering')
                    ->rows(2)
                    ->columnSpanFull(),
                Select::make('deviation')
                    ->label('Afwijking')
                    ->options(
                        collect(Deviation::cases())
                            ->mapWithKeys(fn (Deviation $deviation) => [$deviation->value => ucfirst($deviation->value)])
                            ->all(),
                    )
                    ->native(false),
                TextInput::make('flyers_count')
                    ->label('Flyers')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session.date')->label('Datum')->date()->sortable(),
                TextColumn::make('session.range_name')->label('Baan/vereniging')->wrap(),
                TextColumn::make('distance_m')->label('Afstand (m)'),
                TextColumn::make('rounds_fired')->label('Patronen'),
                TextColumn::make('ammo_type')->label('Munitie'),
                TextColumn::make('deviation')->label('Afwijking'),
            ])
            ->filters([
                Filter::make('periode')
                    ->schema([
                        DatePicker::make('from')->label('Vanaf'),
                        DatePicker::make('until')->label('Tot'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereHas('session', fn (Builder $sq) => $sq->whereDate('date', '>=', $date)))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereHas('session', fn (Builder $sq) => $sq->whereDate('date', '<=', $date)));
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Sessieregel toevoegen'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('session.date', 'desc');
    }
}

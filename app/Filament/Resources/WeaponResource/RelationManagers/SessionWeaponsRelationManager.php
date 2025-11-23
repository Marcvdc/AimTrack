<?php

namespace App\Filament\Resources\WeaponResource\RelationManagers;

use App\Enums\Deviation;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
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
            ->schema([
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
                    ->label('Munitie')
                    ->maxLength(255),
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
                    ->form([
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
                Tables\Actions\CreateAction::make()
                    ->label('Sessieregel toevoegen'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('session.date', 'desc');
    }
}

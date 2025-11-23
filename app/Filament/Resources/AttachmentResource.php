<?php

namespace App\Filament\Resources;

use App\Models\Attachment;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AttachmentResource extends Resource
{
    protected static ?string $model = Attachment::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-paper-clip';

    protected static ?string $navigationLabel = 'Bijlagen';

    protected static ?string $modelLabel = 'Bijlage';

    protected static ?string $pluralModelLabel = 'Bijlagen';

    protected static UnitEnum|string|null $navigationGroup = 'Dagboek';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('original_name')->label('Bestandsnaam')->disabled(),
                TextInput::make('mime_type')->label('MIME-type')->disabled(),
                TextInput::make('size')->label('Grootte (bytes)')->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('original_name')->label('Bestandsnaam')->searchable(),
                TextColumn::make('mime_type')->label('MIME-type'),
                TextColumn::make('size')->label('Grootte (bytes)')->formatStateUsing(fn ($state) => number_format($state).' B'),
                TextColumn::make('session.date')->label('Sessie datum')->date(),
            ])
            ->filters([
                Filter::make('groot')->label('Groter dan 5MB')
                    ->query(fn (Builder $query) => $query->where('size', '>', 5 * 1024 * 1024)),
            ])
            ->actions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => AttachmentResource\Pages\ListAttachments::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('session', fn (Builder $query) => $query->where('user_id', auth()->id()));
    }
}

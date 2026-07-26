<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Gebruikersbeheer (BEHEER), uitsluitend voor app-beheerders (users.is_admin).
 * Lijst van gebruikers met de mogelijkheid om beheerderrechten toe te kennen of
 * in te trekken. De laatste beheerder kan zichzelf/anderen niet degraderen.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Gebruikers';

    protected static ?string $modelLabel = 'Gebruiker';

    protected static ?string $pluralModelLabel = 'Gebruikers';

    protected static string|\UnitEnum|null $navigationGroup = 'BEHEER';

    protected static ?int $navigationSort = 70;

    public static function canViewAny(): bool
    {
        return (bool) (auth()->user()?->is_admin);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) (auth()->user()?->is_admin);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_admin')
                    ->label('Beheerder')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Geregistreerd')
                    ->dateTime('d-m-Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('toggleAdmin')
                    ->label(fn (User $record): string => $record->is_admin ? 'Beheerder intrekken' : 'Maak beheerder')
                    ->icon(fn (User $record): string => $record->is_admin ? 'heroicon-m-shield-exclamation' : 'heroicon-m-shield-check')
                    ->color(fn (User $record): string => $record->is_admin ? 'danger' : 'primary')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        if ($record->is_admin && User::query()->where('is_admin', true)->count() <= 1) {
                            Notification::make()
                                ->title('Er moet minstens één beheerder blijven.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update(['is_admin' => ! $record->is_admin]);

                        Notification::make()
                            ->title($record->is_admin ? 'Gebruiker is nu beheerder.' : 'Beheerderrechten ingetrokken.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources\CoachSessies;

use App\Filament\Resources\CoachSessies\Pages\ListCoachSessies;
use App\Filament\Resources\CoachSessies\Pages\ViewCoachSessie;
use App\Filament\Resources\CoachSessies\Schemas\CoachSessieInfolist;
use App\Filament\Resources\CoachSessies\Tables\CoachSessiesTable;
use App\Models\Session;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CoachSessieResource extends Resource
{
    protected static ?string $model = Session::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Sessies leden';

    protected static ?string $modelLabel = 'Ledensessie';

    protected static ?string $pluralModelLabel = 'Ledensessies';

    protected static string|\UnitEnum|null $navigationGroup = 'INZICHT';

    public static function infolist(Schema $schema): Schema
    {
        return CoachSessieInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CoachSessiesTable::configure($table);
    }

    /**
     * Alleen zichtbaar voor coaches/beheerders van de actieve vereniging.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        $vereniging = $user?->activeVereniging;

        return $vereniging !== null
            && ($user->rolInVereniging($vereniging)?->canCoach() ?? false);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * Beperk tot sessies van leden binnen de actieve vereniging van de coach.
     */
    public static function getEloquentQuery(): Builder
    {
        $vereniging = auth()->user()?->activeVereniging;

        $memberIds = $vereniging
            ? $vereniging->members()->pluck('users.id')
            : collect();

        return parent::getEloquentQuery()
            ->whereIn('user_id', $memberIds)
            ->with('user');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoachSessies::route('/'),
            'view' => ViewCoachSessie::route('/{record}'),
        ];
    }
}

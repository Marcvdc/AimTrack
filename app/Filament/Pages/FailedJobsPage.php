<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Beheer-pagina (BEHEER) met de mislukte queue-jobs. Geen gebruikers-
 * informatie, dus afgeschermd tot admin-users (users.is_admin).
 */
class FailedJobsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Mislukte jobs';

    protected static ?string $title = 'Mislukte queue-jobs';

    protected static string|\UnitEnum|null $navigationGroup = 'BEHEER';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.failed-jobs-page';

    public static function canAccess(): bool
    {
        return (bool) (auth()->user()?->is_admin);
    }
}

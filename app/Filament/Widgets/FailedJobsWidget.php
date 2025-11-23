<?php

namespace App\Filament\Widgets;

use App\Models\FailedJob;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\Concerns\CanPoll;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class FailedJobsWidget extends BaseWidget
{
    use CanPoll;

    protected static ?string $heading = 'Mislukte queue-jobs';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected function getTableQuery(): Builder
    {
        return FailedJob::query()->latest('failed_at');
    }

    protected function getDefaultTableRecordsPerPageSelectOption(): int
    {
        return 5;
    }

    protected function isTablePaginationEnabled(): bool
    {
        return true;
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('job_name')
                ->label('Job')
                ->wrap()
                ->description(fn (FailedJob $record) => $record->connection ?? 'default'),
            TextColumn::make('queue')
                ->label('Queue')
                ->badge()
                ->toggleable(),
            TextColumn::make('exception_message')
                ->label('Foutmelding')
                ->wrap()
                ->limit(80)
                ->tooltip(fn (FailedJob $record) => $record->exception_message ?: null),
            TextColumn::make('failed_at')
                ->label('Mislukt op')
                ->dateTime('d-m-Y H:i'),
        ];
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'Geen mislukte jobs';
    }

    protected function getTableEmptyStateDescription(): ?string
    {
        return 'Top! De queue draait zonder fouten. Gebruik `php artisan queue:failed` voor details of herplaatsing.';
    }

    protected function getPollingInterval(): ?string
    {
        return '30s';
    }
}

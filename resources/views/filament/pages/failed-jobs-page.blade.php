<x-filament-panels::page>
    <p style="font-size: 12px; color: var(--at-muted); line-height: 1.55; margin-bottom: 4px;">
        Technisch overzicht van mislukte queue-jobs (bv. AI-reflecties die niet konden worden gegenereerd).
        Gebruik <span style="font-family: var(--at-font-mono); color: var(--at-text);">php artisan queue:retry</span> om te herplaatsen.
    </p>

    @livewire(\App\Filament\Widgets\FailedJobsWidget::class)
</x-filament-panels::page>

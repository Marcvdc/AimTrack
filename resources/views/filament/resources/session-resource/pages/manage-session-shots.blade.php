<x-filament::page>
    @php
        $sessionLabel = $record->date?->format('d-m-Y') ? 'Sessie ' . $record->date?->format('d-m-Y') : 'Sessie #' . $record->getKey();
    @endphp

    <div class="space-y-6">
        <div>
            <x-filament::breadcrumbs :breadcrumbs="[
                route('filament.admin.resources.sessions.index') => 'Sessies',
                route('filament.admin.resources.sessions.edit', ['record' => $record]) => $sessionLabel,
                'Schoten registreren',
            ]" />
            <h1 class="text-2xl font-semibold mt-4">{{ $sessionLabel }} — schoten registreren</h1>
            <p class="text-sm text-gray-500">Leg schoten vast op de interactieve roos en beheer beurten.</p>
        </div>

        @include('filament.sessions.session-shot-board-panel', ['record' => $record])
    </div>
</x-filament::page>

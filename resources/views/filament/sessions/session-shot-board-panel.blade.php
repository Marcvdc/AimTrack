@php
    $contextReadOnly = ($readOnly ?? null);

    if ($contextReadOnly === null) {
        $contextReadOnly = ! $record->exists || ! \App\Filament\Resources\SessionResource::canEdit($record);
    }
@endphp

<div class="space-y-6">
    <div class="{{ $contextReadOnly ? 'grid lg:grid-cols-2 gap-6' : 'space-y-6' }}">
        <div class="bg-white/70 dark:bg-gray-900/50 rounded-2xl border border-gray-200/70 dark:border-gray-800/70 shadow-sm">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200/70 dark:border-gray-800/70">
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500">Interactieve roos</p>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                        {{ $contextReadOnly ? 'Doelgebied & schoten (alle beurten)' : 'Doelgebied & schoten' }}
                    </h3>
                </div>
                <x-filament::badge color="gray" size="sm">
                    {{ $contextReadOnly ? 'Alleen lezen' : 'Bewerken' }}
                </x-filament::badge>
            </div>
            <div class="px-4 py-5">
                @livewire('session-shot-board', [
                    'session' => $record,
                    'readOnly' => $contextReadOnly,
                ], key('session-shot-board-' . $record->getKey() . '-' . ($contextReadOnly ? 'readonly' : 'editable')))
            </div>
        </div>
    </div>
</div>

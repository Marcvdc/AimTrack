@php
    $contextReadOnly = ($readOnly ?? null);

    if ($contextReadOnly === null) {
        $contextReadOnly = ! $record->exists || ! \App\Filament\Resources\SessionResource::canEdit($record);
    }
    
    $canEdit = !$contextReadOnly;
@endphp

<div class="space-y-4">
    <div class="rounded-3xl border border-gray-200/70 bg-white/85 px-4 py-5 shadow-sm dark:border-gray-800/60 dark:bg-gray-900/60">
        @livewire('session-shot-board', [
            'session' => $record,
            'readOnly' => $contextReadOnly,
        ], key('session-shot-board-' . $record->getKey() . '-' . ($contextReadOnly ? 'readonly' : 'editable')))
    </div>
</div>

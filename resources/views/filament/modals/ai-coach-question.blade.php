<div class="space-y-4">
    <div>
        <h3 class="font-semibold text-gray-900 dark:text-gray-100">Vraag</h3>
        <p class="whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300">{{ $record->question }}</p>
    </div>

    @if ($record->weapon)
        <div>
            <h3 class="font-semibold text-gray-900 dark:text-gray-100">Wapen</h3>
            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $record->weapon->name }}</p>
        </div>
    @endif

    @if ($record->period_from && $record->period_to)
        <div>
            <h3 class="font-semibold text-gray-900 dark:text-gray-100">Periode</h3>
            <p class="text-sm text-gray-700 dark:text-gray-300">
                {{ $record->period_from->format('d/m/Y') }} - {{ $record->period_to->format('d/m/Y') }}
            </p>
        </div>
    @endif

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-gray-100">Antwoord</h3>
        <div class="whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300">{{ $record->answer }}</div>
    </div>

    <div class="text-xs text-gray-500 dark:text-gray-400">
        Gesteld op {{ $record->asked_at->format('d-m-Y H:i') }}
    </div>
</div>

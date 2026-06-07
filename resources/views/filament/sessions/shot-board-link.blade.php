@php
    $url = route('sessions.shots', ['session' => $record->id]);
@endphp

<div class="space-y-4">
    <div class="flex items-center gap-4">
        <a href="{{ $url }}"
           target="_blank"
           class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Open interactieve schotenbord
        </a>
        <span class="text-sm text-gray-500 dark:text-gray-400">
            (opent in nieuw tabblad)
        </span>
    </div>

    <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3 flex-1">
                <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                    Waarom een apart venster?
                </h3>
                <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                    <p>
                        De interactieve schotenbord gebruikt geavanceerde technologie die het beste werkt in een dedicated venster.
                        Dit zorgt voor optimale prestaties en een betere gebruikerservaring.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

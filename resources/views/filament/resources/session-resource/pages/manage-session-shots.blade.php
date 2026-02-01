<x-filament::page>
    @php
        $totalShots = $record->shots()->count();
        $totalPoints = $record->shots()->sum('score');
        $average = $totalShots > 0 ? round($totalPoints / $totalShots, 1) : 0;
        
        // Get turn options for the dropdown
        $turnOptions = range(0, max(0, $record->shots()->max('turn_index') ?? 0));
        
        $sessionLabel = $record->date?->format('d-m-Y') ? 'Sessie: ' . $record->date->format('d-m-Y') : 'Sessie #' . $record->getKey();
    @endphp

    

    <div class="mb-6">
        <div class="flex items-center gap-3">
            <x-filament::badge color="info" size="md">
                Modus Bewerken
            </x-filament::badge>
            <x-filament::badge color="success" size="md">
                Punten {{ number_format($totalPoints) }}
            </x-filament::badge>
            <x-filament::badge color="warning" size="md">
                Schoten {{ number_format($totalShots) }}
            </x-filament::badge>
            <x-filament::badge color="danger" size="md">
                Gemiddelde {{ number_format($average, 1, ',', '.') }}
            </x-filament::badge>
        </div>
    </div>

    <div class="mb-4">
        <x-filament::section icon="heroicon-o-calendar-days" icon-size="sm" class="bg-white/85 dark:bg-gray-900/60 rounded-2xl p-4 shadow-sm">
            <x-slot name="heading">
                {{ $sessionLabel }}
            </x-slot>
        </x-filament::section>
    </div>

    <!-- Beurt Selectie -->
    <div class="mb-6 rounded-3xl border border-gray-200/70 bg-white/80 px-5 py-4 shadow-sm dark:border-gray-800/60 dark:bg-gray-900/60">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        

            <div x-data="{ expanded: false }" class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <x-filament::section icon="heroicon-o-information-circle" icon-size="sm" class="flex-1 bg-white/85 dark:bg-gray-900/60">
                    <x-slot name="heading">
                        <div class="flex items-center gap-2 cursor-pointer" x-on:click="expanded = ! expanded">
                            <span>Interactieve roos</span>
                        </div>
                    </x-slot>
                    
                    <x-slot name="description">
                        Open dit panel voor extra informatie over de interactieve roos
                    </x-slot>
                    
                    <x-slot name="afterHeader">
                        <div class="cursor-pointer" x-on:click="expanded = ! expanded">
                            <x-filament::icon 
                                icon="heroicon-m-chevron-down" 
                                x-bind:class="expanded ? 'rotate-180' : ''"
                                class="transition-transform duration-200 text-gray-500 dark:text-gray-400"
                            />
                        </div>
                    </x-slot>

                    <div x-show="expanded" x-collapse.duration.300ms>
                        Klik om schoten te plaatsen, wissel tussen beurten en verwijder markers met een long-press (of rechtermuisklik).
                        
                        <div class="mt-4">
                            <div class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">Beurt legenda:</div>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($turnOptions as $turn)
                                    <div class="flex items-center gap-1">
                                        <span class="inline-flex h-4 w-4 rounded-full border border-gray-300 dark:border-gray-600" style="background-color: {{ $this->getTurnColor($turn) }}">&nbsp;</span>
                                        <span class="text-xs text-gray-700 dark:text-gray-300">Beurt {{ $turn + 1 }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        
                        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-300">
                            <x-filament::badge color="gray">Lang indrukken = verwijderen</x-filament::badge>
                            <x-filament::badge color="gray">Klik om nieuwe schoten te registreren</x-filament::badge>
                        </div>
                    </div>
                </x-filament::section>
            </div>
        </div>
    </div>

    @include('filament.sessions.session-shot-board-panel', ['record' => $record])
</x-filament::page>

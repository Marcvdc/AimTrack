<x-filament-panels::page>
    <div class="space-y-6">
        <p class="text-sm text-gray-600">
            Beheer je vereniging: de gedeelde Claude-key (fallback voor leden zonder eigen key)
            en de leden met hun rol. De gedeelde key wordt versleuteld opgeslagen en nooit volledig getoond.
        </p>

        <div class="space-y-4">
            {{ $this->form }}

            <div class="flex justify-end gap-3">
                <x-filament::button wire:click="voegLidToe" color="gray" icon="heroicon-m-user-plus">
                    Lid toevoegen
                </x-filament::button>
                <x-filament::button wire:click="save" icon="heroicon-m-check">
                    Key opslaan
                </x-filament::button>
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>

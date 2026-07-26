<x-filament-panels::page>
    <div class="space-y-6">
        <p class="text-sm text-gray-600">
            Maak een nieuwe vereniging aan en wijs een beheerder toe. De beheerder kan daarna
            leden toevoegen en de gedeelde Claude-key instellen via "Mijn vereniging".
        </p>

        <div class="space-y-4">
            {{ $this->form }}

            <div class="flex justify-end">
                <x-filament::button wire:click="aanmaken" icon="heroicon-m-plus">
                    Vereniging aanmaken
                </x-filament::button>
            </div>
        </div>
    </div>
</x-filament-panels::page>

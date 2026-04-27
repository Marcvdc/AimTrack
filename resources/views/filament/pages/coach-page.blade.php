<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section icon="heroicon-o-sparkles">
            <x-slot name="heading">Welkom bij je AI-coach</x-slot>
            <x-slot name="description">
                Stel open vragen over je training, techniek of wapenkeuze. De coach
                gebruikt automatisch jouw recente sessies, wapenoverzicht en eerdere
                AI-reflecties als context.
            </x-slot>

            <div class="prose prose-sm dark:prose-invert max-w-none">
                <p>Voorbeeldvragen:</p>
                <ul>
                    <li>Welke trends zie je in mijn afwijkingen op 25m?</li>
                    <li>Vergelijk mijn laatste sessie met die ervoor.</li>
                    <li>Geef tips voor mijn Glock 17 bij snelvuur op 15m.</li>
                </ul>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    De coach is geen vervanging voor erkende instructeurs. Volg altijd
                    baanregels en wettelijke richtlijnen.
                </p>
            </div>
        </x-filament::section>

        <div class="flex">
            <x-filament::button
                icon="heroicon-o-chat-bubble-left-right"
                x-on:click="window.dispatchEvent(new CustomEvent('copilot-open'))"
            >
                Open de chat
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>

@php
    use Illuminate\Support\Str;
@endphp

<x-filament::page>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                <p><strong>Let op:</strong> de AI-coach geeft geen vervanging voor een erkende instructeur of officiële instanties. Volg altijd baanregels, wetgeving en veiligheidsprotocollen.</p>
            </div>

            <div class="space-y-4">
                {{ $this->form }}
            </div>

            @if ($answer)
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold">Antwoord van de AI-coach</h2>
                        <span class="text-xs text-gray-500">Context: eigen sessiedata, geen officiële adviezen</span>
                    </div>
                    <p class="mt-3 whitespace-pre-line text-gray-800">{{ $answer }}</p>
                </div>
            @endif
        </div>

        <div class="space-y-3">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <h3 class="text-md font-semibold">Recente vragen</h3>
                <p class="text-sm text-gray-600">Alleen jouw eigen vragen en AI-antwoorden worden getoond.</p>
                <div class="mt-3 space-y-3">
                    @forelse ($history as $item)
                        <div class="rounded-md border border-gray-100 bg-gray-50 p-3">
                            <div class="flex items-center justify-between text-xs text-gray-500">
                                <span>{{ optional($item->asked_at)->format('d-m-Y H:i') }}</span>
                                @if ($item->weapon)
                                    <span class="rounded bg-indigo-50 px-2 py-0.5 text-indigo-700">{{ $item->weapon->name }}</span>
                                @else
                                    <span class="text-gray-500">Alle wapens</span>
                                @endif
                            </div>
                            <p class="mt-2 text-sm font-medium text-gray-800">Vraag: {{ Str::limit($item->question, 120) }}</p>
                            <p class="mt-1 text-sm text-gray-700">Antwoord: {{ Str::limit($item->answer, 180) }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-600">Nog geen vragen gesteld.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-filament::page>

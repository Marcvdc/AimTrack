@php
    $createUrl = \App\Filament\Resources\SessionResource::getUrl('create');
@endphp

<div data-testid="sessions-empty-state">
    <x-aimtrack.empty-state :reticle-size="460" :reticle-opacity="0.05" max-width="420px">
        <x-slot:icon>
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="5" width="18" height="16" rx="2"></rect>
                <line x1="3" y1="10" x2="21" y2="10"></line>
                <line x1="8" y1="3" x2="8" y2="7"></line>
                <line x1="16" y1="3" x2="16" y2="7"></line>
            </svg>
        </x-slot:icon>

        <x-slot:title>Nog geen sessies gelogd</x-slot:title>

        <x-slot:description>
            Log je eerste training in ongeveer 30 seconden. Discipline, wapen, baan, score — klaar.
        </x-slot:description>

        <x-slot:actions>
            <a
                href="{{ $createUrl }}"
                data-testid="sessions-empty-create"
                style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; border-radius: 8px; background: var(--at-accent); color: var(--at-cta-text); font-weight: 600; font-size: 13px; text-decoration: none;"
            >
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Eerste sessie loggen
            </a>
            <span data-testid="sessions-empty-demo">
                {{ $this->seedDemoDataAction }}
            </span>
        </x-slot:actions>

        <x-slot:extra>
            <div
                data-testid="sessions-empty-tip"
                style="padding: 14px; background: var(--at-panel); border: 1px solid var(--at-line); border-radius: 10px; font-size: 12px; color: var(--at-muted); text-align: left;"
            >
                <div style="font-family: var(--at-font-mono); font-size: 10px; color: var(--at-accent); letter-spacing: 0.16em;">💡 TIP</div>
                <div style="margin-top: 6px; color: var(--at-text);">
                    Heb je een papieren logboek? Foto's uploaden kan straks ook — AimTrack leest de score uit.
                </div>
            </div>
        </x-slot:extra>
    </x-aimtrack.empty-state>
</div>

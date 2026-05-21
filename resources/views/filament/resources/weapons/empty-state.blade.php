@php
    $createUrl = \App\Filament\Resources\WeaponResource::getUrl('create');
    $templates = \App\Support\StarterTemplates::weapons();
@endphp

<div data-testid="weapons-empty-state">
    <x-aimtrack.empty-state :reticle-size="460" :reticle-opacity="0.05" max-width="480px">
        <x-slot:icon>
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 14h13l3-3h2v6h-3l-2 2h-3l-1 2H7l-1-2H3z"></path>
                <path d="M9 14V9h4v5"></path>
            </svg>
        </x-slot:icon>

        <x-slot:title>Voeg je eerste wapen toe</x-slot:title>

        <x-slot:description>
            Elk wapen krijgt zijn eigen overzicht — sessies, schotaantal, onderhoud, kalibratie.
        </x-slot:description>

        <x-slot:extra>
            <div
                data-testid="weapons-empty-templates"
                style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;"
            >
                @foreach ($templates as $template)
                    @php
                        $cardBg = $template['popular'] ? 'var(--at-accent-12, rgba(100, 244, 179, 0.12))' : 'var(--at-panel)';
                        $cardBorder = $template['popular']
                            ? '1px solid var(--at-accent-25, rgba(100, 244, 179, 0.25))'
                            : '1px solid var(--at-line)';
                        $templateUrl = \App\Filament\Resources\WeaponResource::getUrl('create', ['template' => $template['key']]);
                    @endphp
                    <a
                        href="{{ $templateUrl }}"
                        data-testid="weapons-template-{{ $template['key'] }}"
                        style="padding: 14px; border-radius: 10px; background: {{ $cardBg }}; border: {{ $cardBorder }}; text-align: left; cursor: pointer; text-decoration: none; display: block;"
                    >
                        <div style="font-family: var(--at-font-mono); font-size: 9px; color: var(--at-muted); letter-spacing: 0.14em; text-transform: uppercase;">
                            SJABLOON
                        </div>
                        <div style="font-size: 13px; font-weight: 600; color: var(--at-text); margin-top: 4px;">
                            {{ $template['label'] }}
                        </div>
                        <div style="font-family: var(--at-font-mono); font-size: 11px; color: var(--at-muted); margin-top: 2px;">
                            {{ $template['caliber'] }}
                        </div>
                        @if ($template['popular'])
                            <div style="font-family: var(--at-font-mono); font-size: 9px; color: var(--at-accent); letter-spacing: 0.14em; margin-top: 6px;">
                                ● MEEST GEBRUIKT
                            </div>
                        @endif
                    </a>
                @endforeach
            </div>
        </x-slot:extra>

        <x-slot:actions>
            <a
                href="{{ $createUrl }}"
                data-testid="weapons-empty-create"
                style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; border-radius: 8px; background: var(--at-accent); color: var(--at-cta-text); font-weight: 600; font-size: 13px; text-decoration: none;"
            >
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Voeg wapen toe
            </a>
            <button
                type="button"
                wire:click="seedDemoData"
                data-testid="weapons-empty-demo"
                style="padding: 10px 18px; border-radius: 8px; border: 1px solid var(--at-line); background: transparent; color: var(--at-text); font-size: 13px; cursor: pointer;"
            >
                Demo-data inladen
            </button>
        </x-slot:actions>
    </x-aimtrack.empty-state>
</div>

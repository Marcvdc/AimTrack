<x-filament-panels::page>
    @php
        /** @var \App\Filament\Pages\CoachPage $page */
        $page = $this;
        $onboarding = $page->getOnboarding();
    @endphp

    @if (! $onboarding->aiCoachUnlocked())
        @php
            $threshold = \App\Support\UserOnboardingState::aiCoachThreshold();
            $current = min($onboarding->sessionsCount(), $threshold);
            $remaining = max(0, $threshold - $current);
            $pct = (int) round(($current / max(1, $threshold)) * 100);
        @endphp

        <div data-testid="ai-coach-threshold">
            <x-aimtrack.empty-state :reticle-size="460" :reticle-opacity="0.05" max-width="480px" :icon-accent="true">
                <x-slot:icon>
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3l2 4 4 1-3 3 1 4-4-2-4 2 1-4-3-3 4-1z"></path>
                    </svg>
                </x-slot:icon>

                <x-slot:title>De coach heeft 3 sessies nodig</x-slot:title>

                <x-slot:description>
                    Met minimaal 3 gelogde sessies kan AimTrack patronen herkennen en zinvolle reflecties geven.
                </x-slot:description>

                <x-slot:extra>
                    <div
                        data-testid="ai-coach-progress"
                        style="padding: 16px; background: var(--at-panel); border: 1px solid var(--at-line); border-radius: 10px; text-align: left;"
                    >
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="font-family: var(--at-font-mono); font-size: 26px; font-weight: 600; color: var(--at-text); letter-spacing: -0.02em;">
                                {{ $current }}<span style="color: var(--at-muted); font-size: 14px;">/{{ $threshold }}</span>
                            </div>
                            <div style="flex: 1;">
                                <div style="height: 6px; background: var(--at-line); border-radius: 3px; overflow: hidden;">
                                    <div
                                        data-testid="ai-coach-progress-bar"
                                        style="height: 100%; width: {{ $pct }}%; background: var(--at-accent);"
                                    ></div>
                                </div>
                                <div style="font-size: 11px; color: var(--at-muted); margin-top: 6px; font-family: var(--at-font-mono); letter-spacing: 0.04em; text-transform: uppercase;">
                                    @if ($remaining === 1)
                                        Nog 1 sessie te gaan
                                    @else
                                        Nog {{ $remaining }} sessies te gaan
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </x-slot:extra>

                <x-slot:actions>
                    <a
                        href="{{ $page->getCreateSessionUrl() }}"
                        data-testid="ai-coach-log-session"
                        style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; border-radius: 8px; background: var(--at-accent); color: var(--at-cta-text); font-weight: 600; font-size: 13px; text-decoration: none;"
                    >
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Log volgende sessie
                    </a>
                    <button
                        type="button"
                        wire:click="explainAiCoach"
                        data-testid="ai-coach-explain"
                        style="padding: 10px 18px; border-radius: 8px; border: 1px solid var(--at-line); background: transparent; color: var(--at-text); font-size: 13px; cursor: pointer;"
                    >
                        Hoe werkt de AI?
                    </button>
                </x-slot:actions>
            </x-aimtrack.empty-state>
        </div>
    @else
        <div data-testid="ai-coach-chat-ready" class="space-y-6">
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
    @endif
</x-filament-panels::page>

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

                <x-slot:title>De coach heeft {{ $threshold }} sessies nodig</x-slot:title>

                <x-slot:description>
                    Met minimaal {{ $threshold }} gelogde sessies kan AimTrack patronen herkennen en zinvolle reflecties geven.
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
        @php
            /** @var \App\Services\CoachContextService $coach */
            $coach = $page->getCoachContext();

            $conversations = $coach->recentConversations(6);
            $latestConversation = $coach->latestConversation();
            $lastSession = $coach->lastSession();
            $topWeapon = $coach->topWeapon();
            $trainingGoals = $page->getTrainingGoals();
            $scoreDrift = $page->getScoreDrift();

            $sampleQuestions = array_values(array_filter([
                'Vergelijk met vorige maand',
                'Wat moet ik deze week trainen?',
                $topWeapon ? 'Trekkerafstelling '.$topWeapon->name : 'Hoe verbeter ik mijn groepering?',
                $lastSession ? 'Reflectie op mijn laatste sessie' : null,
            ]));
        @endphp

        <div data-testid="ai-coach-chat-ready" style="display: grid; grid-template-columns: 260px minmax(0, 1fr) 280px; gap: 16px; align-items: stretch; min-height: 540px;">
            <aside style="background: var(--at-panel); border: 1px solid var(--at-line); border-radius: var(--at-r-lg); padding: 16px 12px; display: flex; flex-direction: column; gap: 4px; overflow: auto;">
                <div class="at-label" style="padding: 4px 8px 10px;">RECENTE GESPREKKEN</div>
                @forelse ($conversations as $conv)
                    @php
                        $isActive = $latestConversation && $latestConversation->id === $conv->id;
                    @endphp
                    <div wire:key="conv-{{ $conv->id }}" style="padding: 10px; border-radius: var(--at-r-md); background: {{ $isActive ? 'var(--at-panel-2)' : 'transparent' }}; border: 1px solid {{ $isActive ? 'var(--at-line)' : 'transparent' }};">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            @if ($isActive)
                                <span style="width: 6px; height: 6px; border-radius: 50%; background: var(--at-accent);"></span>
                            @endif
                            <div style="font-size: 12px; color: var(--at-text); font-weight: {{ $isActive ? 600 : 500 }}; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                {{ $conv->title ?: 'Gesprek '.substr((string) $conv->id, 0, 6) }}
                            </div>
                        </div>
                        <div style="font-family: var(--at-font-mono); font-size: 10px; color: var(--at-muted); margin-top: 2px; margin-left: {{ $isActive ? '12px' : '0' }};">
                            {{ \Illuminate\Support\Carbon::parse($conv->updated_at)->diffForHumans() }}
                        </div>
                    </div>
                @empty
                    <div style="padding: 16px 8px; color: var(--at-muted); font-size: 12px; line-height: 1.55;">
                        Nog geen gesprekken met de coach. Open de chat hieronder om je eerste vraag te stellen.
                    </div>
                @endforelse
            </aside>

            <div style="background: var(--at-panel); border: 1px solid var(--at-line); border-radius: var(--at-r-lg); display: flex; flex-direction: column; min-width: 0;">
                <div style="padding: 20px 24px; border-bottom: 1px solid var(--at-line);">
                    <div class="at-label">AI-COACH · CONTEXT-RIJK GESPREK</div>
                    <h2 style="font-family: var(--at-font-display); font-size: 22px; font-weight: 600; letter-spacing: -0.01em; margin: 6px 0 0; color: var(--at-text);">Stel een vraag aan je coach</h2>
                    <p style="margin: 6px 0 0; font-size: 12px; color: var(--at-muted); line-height: 1.5;">
                        De coach gebruikt automatisch je laatste sessies, wapens en eerdere reflecties.
                        Antwoorden draaien op je <span style="color: var(--at-accent);">eigen instance</span> — geen data verlaat de server.
                    </p>
                </div>

                <div style="flex: 1; padding: 24px; display: flex; flex-direction: column; gap: 20px; min-height: 0; overflow: auto;">
                    <div style="background: color-mix(in srgb, var(--at-accent) 6%, transparent); border: 1px solid var(--at-accent-25); border-radius: var(--at-r-md); padding: 16px;">
                        <div class="at-label" style="color: var(--at-accent);">SUGGESTIE</div>
                        <div style="font-size: 13.5px; line-height: 1.55; color: var(--at-text); margin-top: 6px;">
                            @if ($lastSession)
                                Wil je een reflectie op <strong>sessie {{ 'S-'.str_pad((string) $lastSession->id, 4, '0', STR_PAD_LEFT) }}</strong>
                                ({{ $lastSession->date?->translatedFormat('d M') ?? '—' }})? Of stel een open vraag over je training.
                            @else
                                Log eerst een sessie of stel direct een open vraag — de coach werkt ook zonder data.
                            @endif
                        </div>
                    </div>

                    <div>
                        <div class="at-label" style="margin-bottom: 8px;">VOORBEELDVRAGEN</div>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            @foreach ($sampleQuestions as $q)
                                <button
                                    type="button"
                                    x-on:click="window.dispatchEvent(new CustomEvent('copilot-open'))"
                                    style="padding: 5px 10px; border-radius: 999px; border: 1px solid var(--at-line); background: transparent; color: var(--at-muted); font-size: 11px; font-family: var(--at-font-mono); letter-spacing: 0.04em; cursor: pointer;"
                                >{{ $q }}</button>
                            @endforeach
                        </div>
                    </div>

                    @if (count($scoreDrift) >= 2)
                        <x-aimtrack.bracket-frame :rounded="8" :padding="16" style="display: flex; flex-direction: column; gap: 10px;">
                            <div class="at-label" style="color: var(--at-accent);">SCORE-DRIFT · GEM. PER SCHOT · LAATSTE SESSIES</div>
                            <x-aimtrack.sparkline :data="array_values($scoreDrift)" :width="520" :height="70" :stroke-width="2" :fill="true" color="var(--at-warn)" />
                            <div style="display: flex; justify-content: space-between; font-family: var(--at-font-mono); font-size: 9px; color: var(--at-muted); letter-spacing: 0.14em;">
                                <span>SCHOT {{ array_key_first($scoreDrift) }}</span>
                                <span>SCHOT {{ array_key_last($scoreDrift) }}</span>
                            </div>
                        </x-aimtrack.bracket-frame>
                    @endif

                    <div style="margin-top: auto; display: flex; align-items: center; gap: 10px;">
                        <button
                            type="button"
                            x-on:click="window.dispatchEvent(new CustomEvent('copilot-open'))"
                            style="padding: 10px 16px; border-radius: var(--at-r-md); border: none; background: var(--at-accent); color: var(--at-cta-text); font-weight: 600; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;"
                        >
                            Open de chat
                        </button>
                        <span style="font-family: var(--at-font-mono); font-size: 10px; color: var(--at-muted); letter-spacing: 0.12em;">⌃⇧K · OPENT FLOATING CHAT</span>
                    </div>
                </div>
            </div>

            <aside style="background: var(--at-panel); border: 1px solid var(--at-line); border-radius: var(--at-r-lg); padding: 20px 18px; display: flex; flex-direction: column; gap: 14px; overflow: auto;">
                <div class="at-label">CONTEXT IN GESPREK</div>

                @if ($lastSession)
                    @php
                        $sessionLabel = 'S-'.str_pad((string) $lastSession->id, 4, '0', STR_PAD_LEFT);
                    @endphp
                    <div style="padding: 12px; background: var(--at-panel-2); border: 1px solid var(--at-line); border-radius: var(--at-r-md); display: flex; align-items: center; gap: 10px;">
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 12px; color: var(--at-text); font-weight: 600;">{{ $sessionLabel }} · {{ $lastSession->date?->translatedFormat('d M') ?? '—' }}</div>
                            <div style="font-size: 11px; color: var(--at-muted); font-family: var(--at-font-mono);">{{ $lastSession->range_name ?? '—' }}</div>
                        </div>
                        <span style="display: inline-flex; align-items: center; padding: 2px 6px; border-radius: 4px; background: var(--at-accent-12); color: var(--at-accent); border: 1px solid var(--at-accent-25); font-family: var(--at-font-mono); font-size: 9px; letter-spacing: 0.08em; text-transform: uppercase;">IN</span>
                    </div>
                @endif

                @if ($topWeapon)
                    <div style="padding: 12px; background: var(--at-panel-2); border: 1px solid var(--at-line); border-radius: var(--at-r-md); display: flex; align-items: center; gap: 10px;">
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 12px; color: var(--at-text); font-weight: 600;">{{ $topWeapon->name }}</div>
                            <div style="font-size: 11px; color: var(--at-muted); font-family: var(--at-font-mono);">{{ $topWeapon->caliber ?? '—' }}</div>
                        </div>
                        <span style="display: inline-flex; align-items: center; padding: 2px 6px; border-radius: 4px; background: var(--at-accent-12); color: var(--at-accent); border: 1px solid var(--at-accent-25); font-family: var(--at-font-mono); font-size: 9px; letter-spacing: 0.08em; text-transform: uppercase;">IN</span>
                    </div>
                @endif

                @unless ($lastSession || $topWeapon)
                    <div style="padding: 12px; color: var(--at-muted); font-size: 12px; line-height: 1.55;">
                        Geen sessies of wapens gevonden. De coach werkt nog zonder context.
                    </div>
                @endunless

                <div class="at-label" style="margin-top: 8px;">VOORGESTELDE DOELEN</div>
                @forelse ($trainingGoals as $goal)
                    <div wire:key="goal-{{ $goal->id }}" style="padding: 12px; background: var(--at-panel-2); border: 1px solid var(--at-line); border-radius: var(--at-r-md);">
                        <div style="display: flex; align-items: flex-start; gap: 8px;">
                            <button
                                type="button"
                                wire:click="completeGoal({{ $goal->id }})"
                                title="Markeer als afgerond"
                                style="flex: 0 0 16px; width: 16px; height: 16px; margin-top: 2px; border-radius: 4px; border: 1.5px solid var(--at-accent); background: transparent; cursor: pointer; padding: 0;"
                            ></button>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-size: 12px; color: var(--at-text); font-weight: 600;">{{ $goal->title }}</div>
                                @if (filled($goal->detail))
                                    <div style="font-size: 11px; color: var(--at-muted); margin-top: 2px; line-height: 1.5;">{{ $goal->detail }}</div>
                                @endif
                                @if ($goal->source === \App\Enums\TrainingGoalSource::Ai)
                                    <div style="font-family: var(--at-font-mono); font-size: 9px; color: var(--at-accent); letter-spacing: 0.08em; text-transform: uppercase; margin-top: 4px;">AI-suggestie</div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div style="font-size: 11px; color: var(--at-muted); line-height: 1.55;">
                        Nog geen doelen. Vraag de coach om een trainingsdoel voor te stellen.
                    </div>
                @endforelse

                <div class="at-label" style="margin-top: 8px;">PRIVACY</div>
                <div style="font-size: 11px; color: var(--at-muted); line-height: 1.6;">
                    Antwoorden gegenereerd op je <span style="color: var(--at-accent);">eigen instance</span>. Geen data verlaat de server.
                </div>
            </aside>
        </div>
    @endif
</x-filament-panels::page>

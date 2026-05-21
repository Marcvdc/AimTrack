@php
    use Illuminate\Support\Carbon;

    /** @var \App\Services\CoachContextService $coach */
    $coach = $this->getCoachContext();

    $conversations = $coach->recentConversations(6);
    $latestConversation = $coach->latestConversation();
    $lastSession = $coach->lastSession();
    $topWeapon = $coach->topWeapon();

    $sampleQuestions = [
        'Vergelijk met vorige maand',
        'Wat trainen deze week?',
        'Trekkerafstelling LP500',
        'WM-4 status',
    ];
@endphp

<x-filament-panels::page>
    <div style="display: grid; grid-template-columns: 260px minmax(0, 1fr) 280px; gap: 16px; align-items: stretch; min-height: 540px;">
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
                        {{ Carbon::parse($conv->updated_at)->diffForHumans() }}
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
                            <span style="padding: 5px 10px; border-radius: 999px; border: 1px solid var(--at-line); color: var(--at-muted); font-size: 11px; font-family: var(--at-font-mono); letter-spacing: 0.04em;">{{ $q }}</span>
                        @endforeach
                    </div>
                </div>

                <div style="margin-top: auto; display: flex; align-items: center; gap: 10px;">
                    <button
                        type="button"
                        x-on:click="window.dispatchEvent(new CustomEvent('copilot-open'))"
                        style="padding: 10px 16px; border-radius: var(--at-r-md); border: none; background: var(--at-accent); color: var(--at-cta-text); font-weight: 600; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;"
                    >
                        Open coach
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

            <div class="at-label" style="margin-top: 8px;">PRIVACY</div>
            <div style="font-size: 11px; color: var(--at-muted); line-height: 1.6;">
                Antwoorden gegenereerd op je <span style="color: var(--at-accent);">eigen instance</span>. Geen data verlaat de server.
            </div>
        </aside>
    </div>
</x-filament-panels::page>

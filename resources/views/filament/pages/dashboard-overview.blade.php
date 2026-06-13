@php
    /** @var \App\Filament\Pages\Dashboard $page */
    $page = $this;
    $summary = $page->getSummary();

    $monthDelta = $summary->sessionsThisMonthDelta();
    $monthDeltaLabel = $monthDelta >= 0 ? '+'.$monthDelta : (string) $monthDelta;
    $bestSerie = $summary->bestSeriesScore();
    $pending = $summary->pendingAiReflections();

    $disciplines = $summary->disciplineProgress();
    $goals = $page->getTrainingGoals();
    $knsaLinks = $page->getKnsaLinks();
@endphp

<x-filament-panels::page>
    <x-aimtrack.page-header
        title="Dashboard"
        subtitle="Je voortgang in één oogopslag — sessies, leergebieden en kennisbronnen."
        :reticle-opacity="0.07"
        :reticle-size="200"
    />

    <div class="at-kpi-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
        <x-aimtrack.stat-card
            label="Sessies / mnd"
            :value="(string) $summary->sessionsThisMonth()"
            :sub="$monthDeltaLabel.' vs vorige maand'"
            :sub-tone="$monthDelta >= 0 ? 'accent' : 'warn'"
        />
        <x-aimtrack.stat-card
            label="Schoten (30d)"
            :value="number_format($summary->shotsLast30d(), 0, ',', '.')"
            sub="laatste 30 dagen"
        />
        <x-aimtrack.stat-card
            label="Beste serie"
            :value="$bestSerie !== null ? (string) $bestSerie : '—'"
            :sub="$bestSerie !== null ? '10-shot serie' : 'geen volle serie'"
            :value-tone="$bestSerie !== null ? 'accent' : 'muted'"
        />
        <x-aimtrack.stat-card
            label="AI-reflecties"
            :value="(string) $summary->aiReflectionCount()"
            :sub="$pending > 0 ? $pending.' in wachtrij' : 'alles bijgewerkt'"
        />
    </div>

    <div class="at-body-2col" style="display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 16px; align-items: start;">
        <div style="display: flex; flex-direction: column; gap: 16px; min-width: 0;">
            {{-- Leergebieden · voortgang per discipline --}}
            <div style="background: var(--at-panel); border: 1px solid var(--at-line); border-radius: var(--at-r-lg); overflow: hidden;">
                <div style="padding: 14px 16px; border-bottom: 1px solid var(--at-line); display: flex; align-items: center; gap: 10px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--at-accent)" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 17 9 11 13 14 21 6" /><polyline points="14 6 21 6 21 13" /></svg>
                    <div style="font-size: 13px; font-weight: 600; color: var(--at-text);">Leergebieden · voortgang</div>
                    <div class="at-label" style="margin-left: auto;">{{ $disciplines->count() }} disciplines</div>
                </div>
                @forelse ($disciplines as $discipline)
                    <div style="display: flex; align-items: center; gap: 16px; padding: 14px 16px; @if (! $loop->last) border-bottom: 1px solid var(--at-line); @endif">
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 13px; font-weight: 600; color: var(--at-text);">{{ $discipline['label'] }}</div>
                            <div style="font-family: var(--at-font-mono); font-size: 10px; color: var(--at-muted); margin-top: 2px;">{{ $discipline['sessions'] }} sessies · {{ $discipline['shots'] }} schoten</div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-family: var(--at-font-mono); font-size: 18px; font-weight: 600; color: var(--at-text);">{{ $discipline['avg'] > 0 ? number_format($discipline['avg'], 1, ',', '.') : '—' }}</div>
                            <div style="font-family: var(--at-font-mono); font-size: 10px; color: {{ $discipline['trend'] >= 0 ? 'var(--at-accent)' : 'var(--at-warn)' }};">
                                {{ $discipline['trend'] >= 0 ? '▲ +'.$discipline['trend'] : '▼ '.$discipline['trend'] }}
                            </div>
                        </div>
                        @if (count($discipline['series']) >= 2)
                            <x-aimtrack.sparkline
                                :data="$discipline['series']"
                                :width="96"
                                :height="36"
                                :fluid="true"
                                :color="$discipline['trend'] >= 0 ? 'var(--at-accent)' : 'var(--at-warn)'"
                            />
                        @endif
                    </div>
                @empty
                    <div style="padding: 24px 16px; color: var(--at-muted); font-size: 12px; text-align: center;">Nog geen disciplines om voortgang te tonen.</div>
                @endforelse
            </div>

            {{-- Trainingsdoelen --}}
            <div style="background: var(--at-panel); border: 1px solid var(--at-line); border-radius: var(--at-r-lg); overflow: hidden;">
                <div style="padding: 14px 16px; border-bottom: 1px solid var(--at-line); display: flex; align-items: center; gap: 10px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--at-accent)" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9" /><circle cx="12" cy="12" r="5" /><circle cx="12" cy="12" r="1.2" fill="var(--at-accent)" /></svg>
                    <div style="font-size: 13px; font-weight: 600; color: var(--at-text);">Trainingsdoelen</div>
                    <a href="{{ \App\Filament\Pages\CoachPage::getUrl() }}" class="at-label" style="margin-left: auto; color: var(--at-accent); text-decoration: none;">AI-coach →</a>
                </div>
                @forelse ($goals as $goal)
                    <div style="display: flex; align-items: flex-start; gap: 10px; padding: 12px 16px; @if (! $loop->last) border-bottom: 1px solid var(--at-line); @endif">
                        <div style="flex: 0 0 16px; width: 16px; height: 16px; margin-top: 2px; border-radius: 4px; border: 1.5px solid var(--at-accent);"></div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-size: 12px; color: var(--at-text); font-weight: 600;">{{ $goal->title }}</div>
                            @if (filled($goal->detail))
                                <div style="font-size: 11px; color: var(--at-muted); margin-top: 2px; line-height: 1.5;">{{ $goal->detail }}</div>
                            @endif
                        </div>
                        @if ($goal->source === \App\Enums\TrainingGoalSource::Ai)
                            <span style="font-family: var(--at-font-mono); font-size: 9px; color: var(--at-accent); letter-spacing: 0.08em; text-transform: uppercase;">AI</span>
                        @endif
                    </div>
                @empty
                    <div style="padding: 20px 16px; color: var(--at-muted); font-size: 12px;">Nog geen open doelen. Vraag de AI-coach om een trainingsdoel voor te stellen.</div>
                @endforelse
            </div>
        </div>

        {{-- KNSA kennisbank --}}
        <div style="display: flex; flex-direction: column; gap: 16px; min-width: 0;">
            <x-aimtrack.bracket-frame :rounded="8" :padding="0" style="overflow: hidden;">
                <div style="padding: 14px 16px; border-bottom: 1px solid var(--at-line); display: flex; align-items: center; gap: 10px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--at-accent)" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /></svg>
                    <div style="font-size: 13px; font-weight: 600; color: var(--at-text);">KNSA · kennisbank</div>
                </div>
                @forelse ($knsaLinks as $link)
                    <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer"
                        style="display: block; padding: 12px 16px; text-decoration: none; @if (! $loop->last) border-bottom: 1px solid var(--at-line); @endif">
                        <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: var(--at-text);">
                            {{ $link['title'] }}
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="var(--at-accent)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" /><polyline points="15 3 21 3 21 9" /><line x1="10" y1="14" x2="21" y2="3" /></svg>
                        </div>
                        @if (! empty($link['description']))
                            <div style="font-size: 11px; color: var(--at-muted); margin-top: 4px; line-height: 1.5;">{{ $link['description'] }}</div>
                        @endif
                    </a>
                @empty
                    <div style="padding: 20px 16px; color: var(--at-muted); font-size: 12px;">Geen KNSA-links geconfigureerd.</div>
                @endforelse
                <div style="padding: 10px 16px; font-family: var(--at-font-mono); font-size: 10px; color: var(--at-muted); letter-spacing: 0.08em; border-top: 1px solid var(--at-line);">BRON · KNSA.NL</div>
            </x-aimtrack.bracket-frame>
        </div>
    </div>
</x-filament-panels::page>

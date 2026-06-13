@php
    use App\Services\SessionStatsService;

    /** @var \App\Services\RangeConsoleSummaryService $summary */
    $summary = $this->getRangeConsoleSummary();

    $monthDelta = $summary->sessionsThisMonthDelta();
    $monthDeltaLabel = $monthDelta >= 0 ? '+'.$monthDelta : (string) $monthDelta;
    $pending = $summary->pendingAiReflections();
    $lastSession = $summary->lastSession();
    $lastStats = $lastSession ? new SessionStatsService($lastSession) : null;
    $reflection = $summary->latestReflection();
    $trend = $summary->trend30d();
    $bestSerie = $summary->bestSeriesScore();
    $weaponUsage = $summary->weaponUsage();
@endphp

<x-filament-panels::page>
    <x-aimtrack.page-header
        title="Sessies"
        :subtitle="'Overzicht van je trainingen · '.$summary->totalSessions().' totaal · gefilterd op afgelopen 30 dagen'"
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
            label="Schoten totaal"
            :value="number_format($summary->shotsLast30d(), 0, ',', '.')"
            sub="laatste 30d"
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
        <div style="min-width: 0; display: flex; flex-direction: column; gap: 16px;">
            {{ $this->table }}

            @if ($weaponUsage->isNotEmpty())
                <div style="background: var(--at-panel); border: 1px solid var(--at-line); border-radius: var(--at-r-lg); overflow: hidden;">
                    <div style="padding: 14px 16px; border-bottom: 1px solid var(--at-line); display: flex; align-items: center; gap: 10px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--at-accent)" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 14h13l3-3h2v6h-3l-2 2h-3l-1 2H7l-1-2H3z" />
                            <path d="M9 14V9h4v5" />
                        </svg>
                        <div style="font-size: 13px; font-weight: 600; color: var(--at-text);">Wapens · gebruik</div>
                        <div class="at-label" style="margin-left: auto;">{{ $weaponUsage->count() }} actief</div>
                    </div>
                    <div class="at-weapon-usage-grid" style="display: grid; grid-template-columns: repeat({{ min(3, max(1, $weaponUsage->count())) }}, 1fr);">
                        @foreach ($weaponUsage as $weapon)
                            <div style="padding: 16px; display: flex; flex-direction: column; gap: 8px; @if (! $loop->last) border-right: 1px solid var(--at-line); @endif">
                                <div class="at-label">{{ $weapon['type'] }}@if ($weapon['caliber'] !== '') · {{ $weapon['caliber'] }}@endif</div>
                                <div style="font-size: 14px; font-weight: 600; color: var(--at-text);">{{ $weapon['name'] }}</div>
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                                    <div>
                                        <div style="font-family: var(--at-font-mono); font-size: 18px; font-weight: 600; color: var(--at-text);">{{ number_format($weapon['avg'], 1, ',', '.') }}</div>
                                        <div style="font-family: var(--at-font-mono); font-size: 10px; color: var(--at-muted);">{{ $weapon['sessions'] }} sessies · {{ $weapon['shots'] }} schoten</div>
                                    </div>
                                    @if (count($weapon['series']) >= 2)
                                        <x-aimtrack.sparkline
                                            :data="$weapon['series']"
                                            :width="80"
                                            :height="32"
                                            :fluid="true"
                                            :color="$weapon['trend'] >= 0 ? 'var(--at-accent)' : 'var(--at-warn)'"
                                        />
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div style="display: flex; flex-direction: column; gap: 16px; min-width: 0;">
            <div style="background: var(--at-panel); border: 1px solid var(--at-line); border-radius: var(--at-r-lg); overflow: hidden;">
                <div style="padding: 14px 16px; border-bottom: 1px solid var(--at-line); display: flex; align-items: center; gap: 10px;">
                    <div style="font-size: 13px; font-weight: 600; color: var(--at-text);">Laatste sessie</div>
                    <div class="at-label" style="margin-left: auto;">{{ $lastSession ? 'S-'.str_pad((string) $lastSession->id, 4, '0', STR_PAD_LEFT) : '—' }}</div>
                </div>
                <div style="padding: 18px; display: flex; flex-direction: column; align-items: center; gap: 12px;">
                    @if ($lastSession && $lastStats && $lastStats->totalShots() > 0)
                        @php
                            $hits = $lastSession->shots->map(fn ($shot) => [
                                'x' => (float) ($shot->x_normalized ?? 0) - 0.5,
                                'y' => (float) ($shot->y_normalized ?? 0) - 0.5,
                                'r' => (int) ($shot->ring ?? 0),
                            ])->all();
                        @endphp
                        <x-aimtrack.target-rings :size="200" :hits="$hits" />
                        <div class="at-last-session-stats" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; width: 100%;">
                            <div>
                                <div class="at-label" style="letter-spacing: 0.14em;">Score</div>
                                <div style="font-family: var(--at-font-mono); font-size: 18px; font-weight: 600; color: var(--at-accent);">{{ $lastStats->totalScore() }}</div>
                            </div>
                            <div>
                                <div class="at-label" style="letter-spacing: 0.14em;">Beste</div>
                                <div style="font-family: var(--at-font-mono); font-size: 18px; font-weight: 600; color: var(--at-text);">{{ $lastStats->bestShot() ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="at-label" style="letter-spacing: 0.14em;">Groep</div>
                                <div style="font-family: var(--at-font-mono); font-size: 18px; font-weight: 600; color: var(--at-text);">{{ $lastStats->groupMm() !== null ? $lastStats->groupMm() : '—' }}<span style="font-size: 11px; color: var(--at-muted);">mm</span></div>
                            </div>
                        </div>
                    @else
                        <div style="padding: 32px 8px; color: var(--at-muted); font-size: 12px; text-align: center;">Nog geen sessies gelogd</div>
                    @endif
                </div>
            </div>

            <x-aimtrack.ai-reflection-card
                :reflection="$reflection"
                :session-id="$reflection?->session_id ? 'S-'.str_pad((string) $reflection->session_id, 4, '0', STR_PAD_LEFT) : null"
                :generated-at="$reflection?->updated_at"
            />

            <div style="background: var(--at-panel); border: 1px solid var(--at-line); border-radius: var(--at-r-lg); overflow: hidden;">
                <div style="padding: 14px 16px; border-bottom: 1px solid var(--at-line); display: flex; align-items: center; gap: 10px;">
                    <div style="font-size: 13px; font-weight: 600; color: var(--at-text);">Trend</div>
                    <div class="at-label" style="margin-left: auto;">30d</div>
                </div>
                <div style="padding: 14px;">
                    <x-aimtrack.sparkline :data="array_values($trend)" :width="280" :height="70" :fill="true" :fluid="true" />
                    @if (count($trend) >= 2)
                        <div style="display: flex; justify-content: space-between; margin-top: 8px; font-family: var(--at-font-mono); font-size: 10px; color: var(--at-muted); letter-spacing: 0.08em;">
                            <span>{{ \Illuminate\Support\Carbon::parse(array_key_first($trend))->format('d M') }}</span>
                            <span>{{ \Illuminate\Support\Carbon::parse(array_key_last($trend))->format('d M') }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>

<x-filament::page>
    @php
        $decimal = (bool) (auth()->user()?->preference('decimal_notation') ?? true);
        $scoring = app(\App\Services\Sessions\ShotScoringService::class);
        $shots = $record->shots()->get();
        $totalShots = $shots->count();
        $totalPoints = $decimal
            ? round($shots->sum(fn ($shot) => $scoring->decimalScore((float) $shot->distance_from_center)), 1)
            : (int) $shots->sum('score');
        $average = $totalShots > 0 ? round($totalPoints / $totalShots, 1) : 0;
        $fmt = fn ($value): string => number_format((float) $value, $decimal ? 1 : 0, '.', '');

        $turnOptions = range(0, max(0, (int) ($record->shots()->max('turn_index') ?? 0)));
        $sessionLabel = $record->date ? 'Sessie · ' . $record->date->format('d-m-Y') : 'Sessie #' . $record->getKey();
    @endphp

    

    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
        <div>
            <div class="at-label">Schoten registreren</div>
            <div style="font-size: 18px; font-weight: 600; color: var(--at-text); margin-top: 4px;">{{ $sessionLabel }}</div>
        </div>
        <span style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: var(--at-r-pill); background: var(--at-accent-12); border: 1px solid var(--at-accent-25); font-family: var(--at-font-mono); font-size: 10px; letter-spacing: 0.14em; color: var(--at-accent);">
            <span style="width: 6px; height: 6px; border-radius: 50%; background: var(--at-accent);"></span>
            MODUS BEWERKEN
        </span>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px;">
        <x-aimtrack.stat-card label="Punten" :value="$fmt($totalPoints)" valueTone="accent" />
        <x-aimtrack.stat-card label="Schoten" :value="(string) $totalShots" />
        <x-aimtrack.stat-card label="Gemiddelde / schot" :value="$totalShots > 0 ? $fmt($average) : '—'" />
    </div>

    <!-- Info / beurt-legenda -->
    <div x-data="{ expanded: false }" style="margin-bottom: 20px; padding: 14px 16px; background: var(--at-panel); border: 1px solid var(--at-line); border-radius: var(--at-r-2xl);">
        <button type="button" x-on:click="expanded = ! expanded"
            style="display: flex; align-items: center; justify-content: space-between; width: 100%; background: none; border: none; color: var(--at-text); cursor: pointer; padding: 0;">
            <span style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600;">
                <x-filament::icon icon="heroicon-o-information-circle" class="h-4 w-4" style="color: var(--at-muted);" />
                Hoe werkt de interactieve roos?
            </span>
            <x-filament::icon icon="heroicon-m-chevron-down" x-bind:class="expanded ? 'rotate-180' : ''" class="h-4 w-4 transition-transform duration-200" style="color: var(--at-muted);" />
        </button>

        <div x-show="expanded" x-collapse.duration.300ms style="margin-top: 12px; font-size: 13px; color: var(--at-muted); line-height: 1.6;">
            Klik op de roos om een schot te plaatsen, wissel tussen beurten met de selector, en verwijder een marker met een long-press (2s) of rechtermuisklik. Score en ring worden automatisch berekend.

            <div style="margin-top: 14px;">
                <div class="at-label" style="margin-bottom: 8px;">Beurt-legenda</div>
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    @foreach ($turnOptions as $turn)
                        <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--at-text);">
                            <span style="display: inline-block; width: 14px; height: 14px; border-radius: 50%; border: 1px solid var(--at-line); background: {{ $this->getTurnColor($turn) }};"></span>
                            Beurt {{ $turn + 1 }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @include('filament.sessions.session-shot-board-panel', ['record' => $record])
</x-filament::page>

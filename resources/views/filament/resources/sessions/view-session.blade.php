@php
    use App\Services\SessionStatsService;
    use Illuminate\Support\Carbon;

    /** @var \App\Models\Session $session */
    $session = $this->getRecord();
    $stats = new SessionStatsService($session);

    $session->loadMissing(['shots', 'sessionWeapons.weapon', 'aiReflection']);

    $totalShots = $stats->totalShots();
    $totalScore = $stats->totalScore();
    $tienen = $stats->tienen();
    $negens = $stats->negens();
    $bestShot = $stats->bestShot();
    $groupMm = $stats->groupMm();
    $cadans = $stats->avgCadansSec();
    $perSerie = $stats->shotsPerSerie();
    $series = $stats->seriesScores($perSerie);
    $dipRange = $stats->dipRange();

    $sessionLabel = 'S-'.str_pad((string) $session->id, 4, '0', STR_PAD_LEFT);
    $dateLabel = $session->date ? Carbon::parse($session->date)->translatedFormat('l d F Y') : '—';

    $firstSessionWeapon = $session->sessionWeapons->first();
    $weapon = $firstSessionWeapon?->weapon;
    $distance = $firstSessionWeapon?->distance_m;
    $disciplineLabel = $weapon
        ? trim(($weapon->weapon_type?->value ?? '').' '.($distance ? $distance.'m' : ''))
        : '—';
    if ($disciplineLabel === '') {
        $disciplineLabel = '—';
    }

    $hits = $session->shots->map(fn ($shot) => [
        'x' => (float) ($shot->x_normalized ?? 0) - 0.5,
        'y' => (float) ($shot->y_normalized ?? 0) - 0.5,
        'r' => (int) ($shot->ring ?? 0),
    ])->all();

    $reflectionRecord = $session->aiReflection;
@endphp

<x-filament-panels::page>
    <div style="display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 16px; align-items: start;">
        <div style="display: flex; flex-direction: column; gap: 16px; min-width: 0;">
            <div style="position: relative; padding: 20px; background: var(--at-panel); border: 1px solid var(--at-line); border-radius: var(--at-r-lg); overflow: hidden;">
                <x-aimtrack.watermark-bg :size="220" :opacity="0.07" :top="-60" :right="-40" />
                @if ($reflectionRecord)
                    <x-aimtrack.monogram-stamp label="WM-4 OK" corner="top-right" />
                @endif
                <div style="position: relative; z-index: 1; display: flex; align-items: flex-start; gap: 24px;">
                    <div style="flex: 1; min-width: 0;">
                        <div class="at-label">SESSIE · {{ $sessionLabel }} · {{ $dateLabel }}</div>
                        <h1 style="font-family: var(--at-font-display); font-size: 26px; font-weight: 600; letter-spacing: -0.01em; margin: 6px 0 0; color: var(--at-text);">
                            {{ ucfirst($disciplineLabel) }} · {{ $totalShots }} schoten
                        </h1>
                        <div style="display: flex; gap: 16px; margin-top: 10px; font-size: 12px; color: var(--at-muted); flex-wrap: wrap;">
                            <span>{{ $session->range_name ?? '—' }}</span>
                            <span>{{ $weapon?->name ?? '—' }}{{ $weapon?->caliber ? ' · '.$weapon->caliber : '' }}</span>
                            <span>{{ $cadans !== null ? round(($cadans * max(1, $totalShots)) / 60, 0).' min' : '—' }}</span>
                        </div>
                    </div>
                    <div style="text-align: right; position: relative;">
                        <div class="at-label">EINDSCORE</div>
                        <div style="font-family: var(--at-font-mono); font-size: 44px; font-weight: 600; color: var(--at-accent); line-height: 1; letter-spacing: -0.02em;">{{ $totalScore }}</div>
                        <div style="font-family: var(--at-font-mono); font-size: 12px; color: var(--at-muted); margin-top: 2px;">{{ $totalShots > 0 ? round($totalScore / $totalShots, 1).' gem' : '—' }}</div>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px;">
                <x-aimtrack.stat-card label="Beste schot" :value="$bestShot !== null ? (string) $bestShot : '—'" />
                <x-aimtrack.stat-card label="Tienen" :value="$tienen.'/'.max(1, $totalShots)" :value-tone="$tienen > 0 ? 'accent' : 'text'" />
                <x-aimtrack.stat-card label="Negens" :value="$negens.'/'.max(1, $totalShots)" />
                <x-aimtrack.stat-card label="Groep" :value="$groupMm !== null ? $groupMm.' mm' : '—'" />
                <x-aimtrack.stat-card label="Gem. cadans" :value="$cadans !== null ? $cadans.' s' : '—'" />
            </div>

            <x-aimtrack.series-card :series="$series" :shots-per-serie="$perSerie" />

            @if ($totalShots > 0)
                <div style="background: var(--at-panel); border: 1px solid var(--at-line); border-radius: var(--at-r-lg); overflow: hidden;">
                    <div style="padding: 14px 16px; border-bottom: 1px solid var(--at-line); display: flex; align-items: center; gap: 10px;">
                        <div style="font-size: 13px; font-weight: 600; color: var(--at-text);">Schot-voor-schot</div>
                        <div class="at-label" style="margin-left: auto;">{{ $totalShots }} schoten</div>
                    </div>
                    <div style="padding: 14px 16px 16px;">
                        <x-aimtrack.shot-strip
                            :shots="$session->shots->pluck('score')->all()"
                            :total-slots="$totalShots"
                            :dip-range="$dipRange"
                            :height="80"
                        />
                    </div>
                </div>
            @else
                <div style="background: var(--at-panel); border: 1px solid var(--at-line); border-radius: var(--at-r-lg); padding: 32px 16px; color: var(--at-muted); font-size: 12px; text-align: center;">
                    Nog geen schoten gelogd voor deze sessie.
                </div>
            @endif
        </div>

        <div style="display: flex; flex-direction: column; gap: 16px; min-width: 0;">
            <div style="background: var(--at-panel); border: 1px solid var(--at-line); border-radius: var(--at-r-lg); overflow: hidden;">
                <div style="padding: 14px 16px; border-bottom: 1px solid var(--at-line); display: flex; align-items: center; gap: 10px;">
                    <div style="font-size: 13px; font-weight: 600; color: var(--at-text);">Hit-patroon</div>
                    <div class="at-label" style="margin-left: auto;">{{ $totalShots }} schoten</div>
                </div>
                <div style="padding: 16px; display: flex; flex-direction: column; align-items: center; gap: 12px;">
                    @if ($totalShots > 0)
                        <x-aimtrack.target-rings :size="240" :hits="$hits" />
                    @else
                        <div style="padding: 40px 8px; color: var(--at-muted); font-size: 12px;">Nog geen hits geregistreerd</div>
                    @endif
                </div>
            </div>

            <x-aimtrack.ai-reflection-card
                :reflection="$reflectionRecord"
                :session-id="$sessionLabel"
                :generated-at="$reflectionRecord?->updated_at"
            />

            @if (filled($session->manual_reflection))
                <div style="background: var(--at-panel); border: 1px solid var(--at-line); border-radius: var(--at-r-lg); overflow: hidden;">
                    <div style="padding: 14px 16px; border-bottom: 1px solid var(--at-line);">
                        <div style="font-size: 13px; font-weight: 600; color: var(--at-text);">Eigen notitie</div>
                    </div>
                    <div style="padding: 16px; font-size: 13px; line-height: 1.55; color: var(--at-text); font-style: italic;">
                        {{ $session->manual_reflection }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>

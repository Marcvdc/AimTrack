@php
    use App\Services\WeaponInsightsService;
    use Illuminate\Support\Carbon;

    /** @var \App\Models\Weapon $weapon */
    $weapon = $this->getRecord();
    $insights = new WeaponInsightsService($weapon);

    $idCode = 'W-'.str_pad((string) $weapon->id, 3, '0', STR_PAD_LEFT);
    $typeLabel = $weapon->weapon_type?->value ?? '—';
    $caliberLabel = $weapon->caliber ?? '—';

    $sessionCount = $insights->sessionCount();
    $totalShots = $insights->totalShots();
    $avgScore = $insights->avgScore();
    $bestScore = $insights->bestScore();
    $bestDate = $insights->bestScoreDate();
    $trendData = $insights->trendData(365);
    $recentSessions = $insights->recentSessions(5);
    $weaponInsight = $weapon->aiWeaponInsight;

    $statusRows = [
        ['Status', $weapon->is_active ? 'Actief · WM-4' : 'Uit gebruik', $weapon->is_active ? 'ok' : null],
        ['Aangeschaft', $weapon->owned_since?->translatedFormat('M Y') ?? '—', null],
        ['Kaliber', $caliberLabel, null],
        ['Type', ucfirst($typeLabel), null],
        ['Opslag', $weapon->storageLocation?->name ?? ($weapon->storage_location ?? '—'), null],
    ];

    $kalibratieRows = [
        ['Korrel', $weapon->korrel_correction ?? '—'],
        ['Vizier', $weapon->vizier_correction ?? '—'],
        ['Trekker', $weapon->trigger_weight_g !== null ? $weapon->trigger_weight_g.' g' : '—'],
        ['Handgreep', $weapon->grip_size ?? '—'],
    ];
@endphp

<x-filament-panels::page>
    <div style="display: grid; grid-template-columns: 340px minmax(0, 1fr); gap: 16px; align-items: start;">
        <div style="display: flex; flex-direction: column; gap: 16px; min-width: 0;">
            <div style="padding: 20px; background: var(--at-panel); border: 1px solid var(--at-line); border-radius: var(--at-r-lg);">
                <div style="aspect-ratio: 16/10; background: linear-gradient(135deg, var(--at-panel-2), var(--at-panel)); border: 1px solid var(--at-line); border-radius: var(--at-r-md); display: flex; align-items: center; justify-content: center; position: relative;">
                    <svg width="65%" height="65%" viewBox="0 0 200 100" style="opacity: 0.7;" role="img" aria-label="Wapen silhouet">
                        <path d="M20 60 L160 60 L168 50 L175 50 L175 60 L185 60 L180 70 L155 70 L150 85 L130 85 L125 75 L40 75 L40 80 L25 80 Z" fill="none" stroke="var(--at-accent)" stroke-width="1.5" />
                        <circle cx="55" cy="68" r="3" fill="var(--at-accent)" />
                        <line x1="40" y1="60" x2="40" y2="75" stroke="var(--at-accent)" stroke-width="1" />
                    </svg>
                    <div style="position: absolute; top: 8px; left: 8px; font-family: var(--at-font-mono); font-size: 9px; color: var(--at-muted); letter-spacing: 0.18em;">FOTO</div>
                </div>

                <div style="margin-top: 14px;">
                    <div class="at-label">{{ strtoupper($typeLabel) }} · {{ $caliberLabel }}</div>
                    <h1 style="font-family: var(--at-font-display); font-size: 22px; font-weight: 600; letter-spacing: -0.01em; margin: 4px 0 0; color: var(--at-text);">{{ $weapon->name }}</h1>
                    <div style="font-family: var(--at-font-mono); font-size: 11px; color: var(--at-muted); margin-top: 6px;">SERIAL · {{ $weapon->serial_number ?? $idCode }}</div>
                </div>

                <div style="height: 1px; background: var(--at-line); margin: 14px 0;"></div>

                @foreach ($statusRows as [$key, $value, $kind])
                    <div style="display: flex; justify-content: space-between; padding: 6px 0; font-size: 12px;">
                        <div style="color: var(--at-muted);">{{ $key }}</div>
                        <div style="color: var(--at-text);">
                            @if (($kind ?? null) === 'ok')
                                <span style="color: var(--at-accent);">● </span>
                            @endif
                            {{ $value }}
                        </div>
                    </div>
                @endforeach
            </div>

            <div style="padding: 16px; background: var(--at-panel); border: 1px solid var(--at-line); border-radius: var(--at-r-lg);">
                <div class="at-label" style="margin-bottom: 10px;">KALIBRATIE</div>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                    @foreach ($kalibratieRows as [$key, $value])
                        <div>
                            <div style="font-family: var(--at-font-mono); font-size: 10px; color: var(--at-muted); letter-spacing: 0.12em;">{{ strtoupper($key) }}</div>
                            <div style="font-family: var(--at-font-mono); font-size: 16px; color: var(--at-text);">{{ $value }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 16px; min-width: 0;">
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
                <x-aimtrack.stat-card label="Sessies" :value="(string) $sessionCount" :sub="$sessionCount > 0 ? 'totaal' : 'nog geen'" />
                <x-aimtrack.stat-card label="Schoten totaal" :value="number_format($totalShots, 0, ',', '.')" />
                <x-aimtrack.stat-card
                    label="Gem. score"
                    :value="$avgScore !== null ? number_format($avgScore, 1, '.', '') : '—'"
                    :value-tone="$avgScore !== null ? 'accent' : 'muted'"
                />
                <x-aimtrack.stat-card
                    label="Beste"
                    :value="$bestScore !== null ? (string) $bestScore : '—'"
                    :sub="$bestDate?->translatedFormat('d M Y') ?? '—'"
                />
            </div>

            <div style="background: var(--at-panel); border: 1px solid var(--at-line); border-radius: var(--at-r-lg); overflow: hidden;">
                <div style="padding: 14px 16px; border-bottom: 1px solid var(--at-line); display: flex; align-items: center; gap: 10px;">
                    <div style="font-size: 13px; font-weight: 600; color: var(--at-text);">Score-trend · alle sessies</div>
                    <div class="at-label" style="margin-left: auto;">{{ count($trendData) }} sessies</div>
                </div>
                <div style="padding: 18px;">
                    <x-aimtrack.sparkline :data="array_values($trendData)" :width="760" :height="140" :stroke-width="2" :fill="true" />
                    @if (count($trendData) >= 2)
                        <div style="display: flex; justify-content: space-between; margin-top: 10px; font-family: var(--at-font-mono); font-size: 10px; color: var(--at-muted); letter-spacing: 0.12em;">
                            <span>{{ Carbon::parse(array_key_first($trendData))->translatedFormat('M Y') }}</span>
                            <span>{{ Carbon::parse(array_key_last($trendData))->translatedFormat('M Y') }}</span>
                        </div>
                    @else
                        <div style="margin-top: 10px; font-family: var(--at-font-mono); font-size: 10px; color: var(--at-muted); letter-spacing: 0.12em; text-align: center;">Onvoldoende data voor trend</div>
                    @endif
                </div>
            </div>

            @if ($weaponInsight)
                @php
                    $insightPatterns = collect($weaponInsight->patterns ?? [])->filter()->values();
                    $insightSuggestions = collect($weaponInsight->suggestions ?? [])->filter()->values();
                @endphp
                <x-aimtrack.bracket-frame :rounded="8" :padding="16" style="display: flex; flex-direction: column; gap: 12px;">
                    <div style="display: flex; align-items: center; gap: 8px; font-size: 11px; font-family: var(--at-font-mono); letter-spacing: 0.12em; text-transform: uppercase; color: var(--at-accent);">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--at-accent)" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" role="presentation"><path d="M12 3l2 4 4 1-3 3 1 4-4-2-4 2 1-4-3-3 4-1z" /></svg>
                        <span>AI-wapeninzicht</span>
                        @if ($weaponInsight->updated_at)
                            <span style="margin-left: auto; color: var(--at-muted);">{{ $weaponInsight->updated_at->diffForHumans() }}</span>
                        @endif
                    </div>
                    @if (filled($weaponInsight->summary))
                        <div style="font-size: 13px; line-height: 1.55; color: var(--at-text);">{{ $weaponInsight->summary }}</div>
                    @endif
                    @if ($insightPatterns->isNotEmpty() || $insightSuggestions->isNotEmpty())
                        <div style="height: 1px; background: var(--at-line);"></div>
                        <div style="display: grid; grid-template-columns: 90px 1fr; gap: 6px; font-size: 12px;">
                            @if ($insightPatterns->isNotEmpty())
                                <div class="at-label" style="color: var(--at-accent);">PATRONEN</div>
                                <div style="color: var(--at-text);">{{ $insightPatterns->implode(' · ') }}</div>
                            @endif
                            @if ($insightSuggestions->isNotEmpty())
                                <div class="at-label">ADVIES</div>
                                <div style="color: var(--at-text);">{{ $insightSuggestions->implode(' · ') }}</div>
                            @endif
                        </div>
                    @endif
                </x-aimtrack.bracket-frame>
            @endif

            <div style="background: var(--at-panel); border: 1px solid var(--at-line); border-radius: var(--at-r-lg); overflow: hidden;">
                <div style="padding: 14px 16px; border-bottom: 1px solid var(--at-line); display: flex; align-items: center; gap: 10px;">
                    <div style="font-size: 13px; font-weight: 600; color: var(--at-text);">Sessies met dit wapen</div>
                    <div class="at-label" style="margin-left: auto;">{{ $recentSessions->count() }} recent · {{ $sessionCount }} totaal</div>
                </div>
                @if ($recentSessions->isEmpty())
                    <div style="padding: 32px 16px; color: var(--at-muted); font-size: 12px; text-align: center;">Nog geen sessies met dit wapen.</div>
                @else
                    <div style="display: grid; grid-template-columns: 100px minmax(0, 1fr) 90px 80px 80px; padding: 8px 16px; font-family: var(--at-font-mono); font-size: 10px; letter-spacing: 0.12em; text-transform: uppercase; color: var(--at-muted); border-bottom: 1px solid var(--at-line);">
                        <div>Datum</div><div>Locatie</div><div>Schoten</div><div>Score</div><div>Status</div>
                    </div>
                    @foreach ($recentSessions as $session)
                        @php
                            $sessionLabel = 'S-'.str_pad((string) $session->id, 4, '0', STR_PAD_LEFT);
                            $sessionScore = (int) ($session->score_total ?? 0);
                            $sessionShots = (int) ($session->rounds_total ?? 0);
                        @endphp
                        <div style="display: grid; grid-template-columns: 100px minmax(0, 1fr) 90px 80px 80px; padding: 12px 16px; border-bottom: 1px solid var(--at-line); align-items: center; font-size: 12px;">
                            <div style="font-family: var(--at-font-mono); color: var(--at-muted); font-size: 11px;">
                                {{ $session->date?->translatedFormat('d M') ?? '—' }}
                                <div style="font-size: 9px; opacity: 0.7;">{{ $sessionLabel }}</div>
                            </div>
                            <div style="color: var(--at-text);">{{ $session->range_name ?? '—' }}</div>
                            <div style="font-family: var(--at-font-mono); color: var(--at-text);">{{ $sessionShots }}</div>
                            <div style="font-family: var(--at-font-mono); color: var(--at-accent); font-weight: 600;">{{ $sessionScore }}</div>
                            <div>
                                @if ($session->ai_reflection_exists)
                                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 6px; border-radius: 4px; background: var(--at-accent-12); color: var(--at-accent); border: 1px solid var(--at-accent-25); font-family: var(--at-font-mono); font-size: 9px; letter-spacing: 0.08em; text-transform: uppercase;">AI</span>
                                @else
                                    <span style="font-family: var(--at-font-mono); font-size: 9px; color: var(--at-muted); letter-spacing: 0.08em; text-transform: uppercase;">open</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>

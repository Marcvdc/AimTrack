@props([
    'series' => [],
    'shotsPerSerie' => 10,
    'goodThreshold' => 95,
    'title' => 'Series',
    'sub' => null,
])

@php
    $series = collect($series)->map(fn ($v) => (float) $v)->all();
    $shotsPerSerie = (int) $shotsPerSerie;
    $goodThreshold = (float) $goodThreshold;
    $maxPossible = (float) ($shotsPerSerie * 10);
    $minPossible = $maxPossible * 0.8;

    $sub = $sub ?? ('tot '.number_format((float) array_sum($series), 1, '.', ''));

    $fmt = static fn (float $value, int $decimals = 1): string => number_format($value, $decimals, '.', '');
    $pct = static function (float $v) use ($minPossible, $maxPossible): float {
        $span = max(0.0001, $maxPossible - $minPossible);
        return max(0.0, min(100.0, (($v - $minPossible) / $span) * 100.0));
    };
@endphp

<div
    {{ $attributes->merge([
        'class' => 'aimtrack-series-card',
        'style' => 'background: var(--at-panel); border: 1px solid var(--at-line); border-radius: var(--at-r-lg); overflow: hidden;',
    ]) }}
>
    <div style="padding: 14px 16px; border-bottom: 1px solid var(--at-line); display: flex; align-items: center; gap: 10px;">
        <div style="font-size: 13px; font-weight: 600; color: var(--at-text); margin: 0;">{{ $title }} · {{ count($series) }} × {{ $shotsPerSerie }} schoten</div>
        <div class="at-label" style="margin-left: auto;">{{ $sub }}</div>
    </div>

    @if (count($series) > 0)
        <div class="aimtrack-series-grid" style="display: grid; grid-template-columns: repeat({{ count($series) }}, 1fr);">
            @foreach ($series as $i => $v)
                @php
                    $good = $v >= $goodThreshold;
                    $valueColor = $good ? 'var(--at-accent)' : 'var(--at-text)';
                    $barColor = $good ? 'var(--at-accent)' : 'var(--at-muted)';
                    $rightBorder = $i < count($series) - 1 ? '1px solid var(--at-line)' : 'none';
                @endphp
                <div style="padding: 14px; border-right: {{ $rightBorder }}; display: flex; flex-direction: column; gap: 4px;">
                    <div class="at-label" style="letter-spacing: 0.12em;">SERIE {{ $i + 1 }}</div>
                    <div style="font-family: var(--at-font-mono); font-size: clamp(16px, 5vw, 22px); font-weight: 600; color: {{ $valueColor }}; letter-spacing: -0.02em;">{{ $fmt($v) }}</div>
                    <div style="height: 4px; background: var(--at-line); border-radius: 2px; position: relative; overflow: hidden;">
                        <div style="position: absolute; inset: 0; width: {{ $fmt($pct($v), 2) }}%; background: {{ $barColor }};"></div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div style="padding: 24px 16px; color: var(--at-muted); font-size: 12px; text-align: center;">—</div>
    @endif
</div>

@props([
    'shots' => [],
    'totalSlots' => null,
    'dipRange' => null,
    'height' => 80,
    'showLegend' => true,
])

@php
    $scores = [];
    foreach ($shots as $s) {
        if (is_numeric($s)) {
            $scores[] = (float) $s;
        } elseif (is_array($s) && isset($s['score'])) {
            $scores[] = (float) $s['score'];
        } elseif (is_object($s) && isset($s->score)) {
            $scores[] = (float) $s->score;
        }
    }
    $totalSlots = $totalSlots !== null ? (int) $totalSlots : max(count($scores), 60);
    $height = (int) $height;

    [$dipFrom, $dipTo] = is_array($dipRange) && count($dipRange) === 2
        ? [(int) $dipRange[0], (int) $dipRange[1]]
        : [null, null];

    $fmt = static fn (float $value): string => rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');

    $barHeight = static function (float $v) use ($height): float {
        $clamped = max(0.0, min(10.0, $v));
        return max(6.0, ($clamped / 10.0) * ($height - 8));
    };

    $emptyCount = max(0, $totalSlots - count($scores));
@endphp

<div
    {{ $attributes->merge([
        'class' => 'aimtrack-shot-strip',
        'style' => "padding: 14px; border: 1px solid var(--at-line); border-radius: var(--at-r-lg); background: var(--at-bg);",
    ]) }}
>
    <div style="display: flex; align-items: flex-end; gap: 2px; height: {{ $height }}px;">
        @foreach ($scores as $i => $v)
            @php
                $h = $barHeight((float) $v);
                $isTen = $v >= 10;
                $inDip = $dipFrom !== null && $i >= $dipFrom && $i <= $dipTo;
                $bg = $isTen
                    ? 'var(--at-accent)'
                    : ($inDip ? 'color-mix(in srgb, var(--at-warn) 70%, transparent)' : 'color-mix(in srgb, var(--at-muted) 40%, transparent)');
            @endphp
            <div title="#{{ $i + 1 }}: {{ $fmt((float) $v) }}" style="flex: 1; height: {{ $fmt($h) }}px; background: {{ $bg }}; border-radius: 1px; min-width: 4px;"></div>
        @endforeach
        @for ($i = 0; $i < $emptyCount; $i++)
            <div style="flex: 1; height: 6px; background: var(--at-line); border-radius: 1px; min-width: 4px; opacity: 0.45;"></div>
        @endfor
    </div>

    <div style="display: flex; justify-content: space-between; margin-top: 8px; font-family: var(--at-font-mono); font-size: 10px; color: var(--at-muted); letter-spacing: 0.08em;">
        <span>1</span>
        <span>{{ (int) round($totalSlots / 4) }}</span>
        <span>{{ (int) round($totalSlots / 2) }}</span>
        <span>{{ (int) round($totalSlots * 3 / 4) }}</span>
        <span>{{ $totalSlots }}</span>
    </div>

    @if ($showLegend)
        <div style="display: flex; gap: 18px; margin-top: 12px; font-size: 11px; color: var(--at-muted); font-family: var(--at-font-mono);">
            <span><span style="display: inline-block; width: 8px; height: 8px; background: var(--at-accent); margin-right: 6px;"></span>10+ ringen</span>
            @if ($dipFrom !== null)
                <span><span style="display: inline-block; width: 8px; height: 8px; background: color-mix(in srgb, var(--at-warn) 70%, transparent); margin-right: 6px;"></span>concentratiedip {{ $dipFrom + 1 }}–{{ $dipTo + 1 }}</span>
            @endif
            <span><span style="display: inline-block; width: 8px; height: 8px; background: color-mix(in srgb, var(--at-muted) 40%, transparent); margin-right: 6px;"></span>normaal</span>
        </div>
    @endif
</div>

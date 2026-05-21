@props([
    'data' => [],
    'width' => 280,
    'height' => 70,
    'color' => null,
    'strokeWidth' => 1.8,
    'fill' => false,
])

@php
    $values = collect($data)->map(fn ($v) => (float) $v)->all();
    $width = (float) $width;
    $height = (float) $height;
    $color = $color ?? 'var(--at-accent)';
    $strokeWidth = (float) $strokeWidth;

    $fmt = static fn (float $value): string => rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.') ?: '0';

    $hasData = count($values) >= 2;
    $min = $hasData ? min($values) : 0.0;
    $max = $hasData ? max($values) : 1.0;
    $range = max(0.0001, $max - $min);

    $points = [];
    foreach ($values as $i => $v) {
        $x = count($values) <= 1 ? $width / 2 : ($i / (count($values) - 1)) * $width;
        $y = $height - 2 - (($v - $min) / $range) * ($height - 6);
        $points[] = $fmt($x).','.$fmt($y);
    }
    $polyline = implode(' ', $points);
    $areaPath = $hasData
        ? "M{$points[0]} L".implode(' L', array_slice($points, 1))." L{$fmt($width)},{$fmt($height)} L0,{$fmt($height)} Z"
        : '';
@endphp

<svg
    {{ $attributes->merge(['class' => 'aimtrack-sparkline', 'style' => 'display: block;']) }}
    width="{{ $fmt($width) }}"
    height="{{ $fmt($height) }}"
    viewBox="0 0 {{ $fmt($width) }} {{ $fmt($height) }}"
    role="img"
    aria-label="Trend"
>
    @if ($hasData)
        @if ($fill)
            <path d="{{ $areaPath }}" fill="{{ $color }}" opacity="0.12" />
        @endif
        <polyline points="{{ $polyline }}" fill="none" stroke="{{ $color }}" stroke-width="{{ $fmt($strokeWidth) }}" stroke-linejoin="round" stroke-linecap="round" />
    @else
        <line x1="0" y1="{{ $fmt($height / 2) }}" x2="{{ $fmt($width) }}" y2="{{ $fmt($height / 2) }}" stroke="var(--at-line)" stroke-width="1" stroke-dasharray="3 4" />
    @endif
</svg>

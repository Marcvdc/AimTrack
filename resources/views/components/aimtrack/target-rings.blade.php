@props([
    'size' => 200,
    'accent' => null,
    'dim' => null,
    'bg' => 'transparent',
    'ringStroke' => 1,
    'showHits' => true,
    'scoreLabels' => false,
    'hits' => null,
])

@php
    $size = (float) $size;
    $ringStroke = (float) $ringStroke;
    $accent = $accent ?? 'var(--at-accent)';
    $dim = $dim ?? 'var(--at-text)';

    $fmt = static fn (float $value): string => rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');

    $cx = $size / 2;
    $cy = $size / 2;
    $maxR = $size * 0.46;
    $rings = [10, 9, 8, 7, 6, 5, 4, 3];
    $ringCount = count($rings);
    $crossReach = $maxR * 1.05;
    $centerDotR = $maxR / $ringCount * 0.5;

    /** @var list<array{x: float, y: float, r: float}> $defaultHits */
    $defaultHits = [
        ['x' => 0.05, 'y' => -0.06, 'r' => 10.0],
        ['x' => -0.10, 'y' => 0.04, 'r' => 9.7],
        ['x' => -0.02, 'y' => 0.12, 'r' => 9.5],
        ['x' => 0.18, 'y' => -0.04, 'r' => 9.0],
        ['x' => 0.08, 'y' => 0.02, 'r' => 9.9],
        ['x' => -0.06, 'y' => -0.14, 'r' => 9.3],
        ['x' => 0.22, 'y' => 0.10, 'r' => 8.6],
        ['x' => -0.18, 'y' => 0.06, 'r' => 8.9],
        ['x' => 0.04, 'y' => -0.08, 'r' => 9.8],
        ['x' => 0.12, 'y' => 0.14, 'r' => 9.1],
    ];
    $hits = $hits ?? $defaultHits;
@endphp

<svg
    {{ $attributes->merge([
        'aria-hidden' => 'true',
        'style' => "display: block; background: {$bg};",
    ]) }}
    width="{{ $fmt($size) }}"
    height="{{ $fmt($size) }}"
    viewBox="0 0 {{ $fmt($size) }} {{ $fmt($size) }}"
>
    @foreach ($rings as $i => $n)
        @php
            $r = $maxR * (($ringCount - $i) / $ringCount);
            $ringFill = $n <= 4 ? $dim : 'transparent';
            $ringFillOpacity = $n <= 4 ? '0.06' : '0';
            $ringStrokeOpacity = $n === 10 ? '0.55' : '0.22';
        @endphp
        <circle cx="{{ $fmt($cx) }}" cy="{{ $fmt($cy) }}" r="{{ $fmt($r) }}" fill="{{ $ringFill }}" fill-opacity="{{ $ringFillOpacity }}" stroke="{{ $dim }}" stroke-opacity="{{ $ringStrokeOpacity }}" stroke-width="{{ $fmt($ringStroke) }}" />
    @endforeach

    <circle cx="{{ $fmt($cx) }}" cy="{{ $fmt($cy) }}" r="{{ $fmt($centerDotR) }}" fill="{{ $dim }}" fill-opacity="0.18" />

    <line x1="{{ $fmt($cx - $crossReach) }}" y1="{{ $fmt($cy) }}" x2="{{ $fmt($cx + $crossReach) }}" y2="{{ $fmt($cy) }}" stroke="{{ $dim }}" stroke-opacity="0.18" stroke-width="{{ $fmt($ringStroke) }}" stroke-dasharray="2 4" />
    <line x1="{{ $fmt($cx) }}" y1="{{ $fmt($cy - $crossReach) }}" x2="{{ $fmt($cx) }}" y2="{{ $fmt($cy + $crossReach) }}" stroke="{{ $dim }}" stroke-opacity="0.18" stroke-width="{{ $fmt($ringStroke) }}" stroke-dasharray="2 4" />

    @if ($scoreLabels)
        @foreach (array_values(array_filter($rings, fn (int $n): bool => $n >= 7)) as $i => $n)
            @php $labelR = $maxR * (($ringCount - $i) / $ringCount); @endphp
            <text x="{{ $fmt($cx + 4) }}" y="{{ $fmt($cy - $labelR + 10) }}" fill="{{ $dim }}" fill-opacity="0.45" style="font: 9px ui-monospace, monospace;">{{ $n }}</text>
        @endforeach
    @endif

    @if ($showHits)
        @foreach ($hits as $h)
            @php
                $hx = $cx + ((float) $h['x']) * $maxR * 0.35;
                $hy = $cy + ((float) $h['y']) * $maxR * 0.35;
                $isTen = ((float) $h['r']) >= 9.5;
            @endphp
            <circle cx="{{ $fmt($hx) }}" cy="{{ $fmt($hy) }}" r="3.2" fill="{{ $accent }}" fill-opacity="{{ $isTen ? '0.95' : '0.55' }}" stroke="{{ $accent }}" stroke-width="{{ $isTen ? '0' : '0.5' }}" />
            @if ($isTen)
                <circle cx="{{ $fmt($hx) }}" cy="{{ $fmt($hy) }}" r="6" fill="none" stroke="{{ $accent }}" stroke-opacity="0.35" stroke-width="1" />
            @endif
        @endforeach
    @endif
</svg>

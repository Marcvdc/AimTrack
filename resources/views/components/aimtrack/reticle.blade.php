@props([
    'size' => 200,
    'stroke' => 1,
    'color' => 'currentColor',
    'tickLen' => 0.18,
    'gap' => 0.06,
    'dot' => false,
    'opacity' => 1,
])

@php
    $size = (float) $size;
    $stroke = (float) $stroke;
    $tickLen = (float) $tickLen;
    $gap = (float) $gap;
    $opacity = (float) $opacity;

    $cx = $size / 2;
    $cy = $size / 2;
    $r = $size * 0.36;
    $tl = $size * $tickLen;
    $g = $size * $gap;
    $dotR = max(2, $size * 0.012);

    $fmt = static fn (float $value): string => rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
@endphp

<svg
    {{ $attributes->merge(['style' => 'display: block; opacity: '.$fmt($opacity).';']) }}
    width="{{ $fmt($size) }}"
    height="{{ $fmt($size) }}"
    viewBox="0 0 {{ $fmt($size) }} {{ $fmt($size) }}"
>
    <circle cx="{{ $fmt($cx) }}" cy="{{ $fmt($cy) }}" r="{{ $fmt($r) }}" fill="none" stroke="{{ $color }}" stroke-width="{{ $fmt($stroke) }}" />
    <line x1="{{ $fmt($cx) }}" y1="{{ $fmt($cy - $r - $g) }}" x2="{{ $fmt($cx) }}" y2="{{ $fmt($cy - $r - $g - $tl) }}" stroke="{{ $color }}" stroke-width="{{ $fmt($stroke) }}" />
    <line x1="{{ $fmt($cx) }}" y1="{{ $fmt($cy + $r + $g) }}" x2="{{ $fmt($cx) }}" y2="{{ $fmt($cy + $r + $g + $tl) }}" stroke="{{ $color }}" stroke-width="{{ $fmt($stroke) }}" />
    <line x1="{{ $fmt($cx - $r - $g) }}" y1="{{ $fmt($cy) }}" x2="{{ $fmt($cx - $r - $g - $tl) }}" y2="{{ $fmt($cy) }}" stroke="{{ $color }}" stroke-width="{{ $fmt($stroke) }}" />
    <line x1="{{ $fmt($cx + $r + $g) }}" y1="{{ $fmt($cy) }}" x2="{{ $fmt($cx + $r + $g + $tl) }}" y2="{{ $fmt($cy) }}" stroke="{{ $color }}" stroke-width="{{ $fmt($stroke) }}" />
    @if ($dot)
        <circle cx="{{ $fmt($cx) }}" cy="{{ $fmt($cy) }}" r="{{ $fmt($dotR) }}" fill="{{ $color }}" />
    @endif
</svg>

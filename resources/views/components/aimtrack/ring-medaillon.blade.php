@props([
    'size' => 200,
    'label' => 'SCORE',
    'value' => '547',
    'sub' => '/600',
    'color' => null,
    'stroke' => 1.5,
])

@php
    $size = (int) $size;
    $stroke = (float) $stroke;
    $color = $color ?? 'var(--at-accent)';

    $labelSize = max(9, $size * 0.05);
    $valueSize = $size * 0.22;
    $subSize = max(10, $size * 0.055);
    $valueGap = $size * 0.02;

    $fmt = static fn (float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
@endphp

<div
    {{ $attributes->merge([
        'class' => 'aimtrack-ring-medaillon',
        'style' => "position: relative; width: 100%; max-width: {$size}px; aspect-ratio: 1; margin-inline: auto;",
    ]) }}
>
    <x-aimtrack.reticle :size="$size" :stroke="$stroke" :color="$color" :dot="false" opacity="0.85" />
    <div style="position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
        <div style="font-family: var(--at-font-mono); font-size: {{ $fmt($labelSize) }}px; letter-spacing: 0.18em; color: var(--at-muted); text-transform: uppercase;">{{ $label }}</div>
        <div style="font-family: var(--at-font-mono); font-size: {{ $fmt($valueSize) }}px; font-weight: 600; color: {{ $color }}; line-height: 1; letter-spacing: -0.02em; margin-top: {{ $fmt($valueGap) }}px;">{{ $value }}</div>
        @if ($sub)
            <div style="font-family: var(--at-font-mono); font-size: {{ $fmt($subSize) }}px; color: var(--at-muted); margin-top: 4px;">{{ $sub }}</div>
        @endif
    </div>
</div>

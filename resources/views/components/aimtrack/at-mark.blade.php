@props([
    'size' => 80,
    'color' => 'currentColor',
])

@php
    $size = (float) $size;
    $fmt = static fn (float $value): string => rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
@endphp

<svg
    {{ $attributes->merge(['style' => 'display: block;']) }}
    width="{{ $fmt($size) }}"
    height="{{ $fmt($size) }}"
    viewBox="0 0 100 100"
>
    <g fill="none" stroke="{{ $color }}" stroke-width="9" stroke-linecap="square">
        <line x1="22" y1="32" x2="78" y2="32" />
        <line x1="50" y1="32" x2="50" y2="84" />
        <line x1="50" y1="32" x2="30" y2="84" />
        <line x1="50" y1="32" x2="70" y2="84" />
        <line x1="36" y1="62" x2="64" y2="62" />
    </g>
</svg>

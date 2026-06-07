@props([
    'label' => 'VERIFIED',
    'variant' => 'solid',
    'size' => 'sm',
    'corner' => null,
    'color' => null,
])

@php
    $color = $color ?? 'var(--at-accent)';
    $isSmall = $size === 'sm';
    $isSolid = $variant === 'solid';

    $padding = $isSmall ? '4px 8px' : '6px 10px';
    $background = $isSolid ? $color : 'transparent';
    $border = $isSolid ? 'none' : "1px solid color-mix(in srgb, {$color} 40%, transparent)";
    $textColor = $isSolid ? 'var(--at-cta-text)' : $color;
    $fontSize = $isSmall ? '9px' : '10px';
    $iconSize = $isSmall ? 12 : 16;

    $cornerStyles = match ($corner) {
        'top-right' => 'position: absolute; top: -10px; right: 16px;',
        'top-left' => 'position: absolute; top: -10px; left: 16px;',
        default => '',
    };
@endphp

<span
    {{ $attributes->merge([
        'class' => 'aimtrack-monogram-stamp',
        'style' => "display: inline-flex; align-items: center; gap: 6px; padding: {$padding}; background: {$background}; border: {$border}; border-radius: 4px;".(($cornerStyles !== '') ? ' '.$cornerStyles : ''),
    ]) }}
>
    <x-aimtrack.at-mark :size="$iconSize" :color="$textColor" />
    <span style="font-family: var(--at-font-mono); font-size: {{ $fontSize }}; letter-spacing: 0.16em; font-weight: 700; color: {{ $textColor }};">{{ $label }}</span>
</span>

@props([
    'size' => 28,
    'color' => null,
    'accent' => null,
])

@php
    $size = (float) $size;
    $color = $color ?? 'var(--at-text)';
    $accent = $accent ?? 'var(--at-accent)';

    $gap = $size * 0.32;
    $textSize = $size * 0.85;

    $fmt = static fn (float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');

    $logoStyle = sprintf(
        'display: inline-block; width: %1$spx; height: %1$spx; background: %2$s; '
        .'-webkit-mask: url(%3$s) center/contain no-repeat; mask: url(%3$s) center/contain no-repeat; '
        .'flex: 0 0 auto;',
        $fmt($size),
        $accent,
        asset('img/aimtrack-logo.svg'),
    );
@endphp

<span
    {{ $attributes->merge([
        'class' => 'aimtrack-wordmark',
        'style' => "display: inline-flex; align-items: center; gap: {$fmt($gap)}px;",
    ]) }}
    aria-label="AimTrack"
>
    <span aria-hidden="true" style="{{ $logoStyle }}"></span>
    <span style="font-family: var(--at-font-display); font-weight: 700; font-size: {{ $fmt($textSize) }}px; letter-spacing: -0.02em; line-height: 1; color: {{ $color }};">Aim<span style="color: {{ $accent }};">Track</span></span>
</span>

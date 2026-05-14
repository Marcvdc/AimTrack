@props([
    'cornerSize' => 14,
    'cornerStroke' => 1.5,
    'cornerColor' => null,
    'bordered' => true,
    'panel' => true,
    'rounded' => 8,
    'padding' => 16,
])

@php
    $cornerColor = $cornerColor ?? 'var(--at-accent)';
    $cornerSize = (int) $cornerSize;
    $cornerStroke = (float) $cornerStroke;
    $rounded = (int) $rounded;
    $padding = (int) $padding;

    $bg = $panel ? 'var(--at-panel)' : 'transparent';
    $border = $bordered ? '1px solid var(--at-line)' : 'none';

    $cornerBase = "position: absolute; width: {$cornerSize}px; height: {$cornerSize}px; pointer-events: none;";
    $cs = $cornerStroke;
    $cc = $cornerColor;
@endphp

<div
    {{ $attributes->merge([
        'class' => 'aimtrack-bracket-frame',
        'style' => "position: relative; background: {$bg}; border: {$border}; border-radius: {$rounded}px; padding: {$padding}px;",
    ]) }}
>
    <span aria-hidden="true" style="{{ $cornerBase }} top: -1px; left: -1px; border-top: {{ $cs }}px solid {{ $cc }}; border-left: {{ $cs }}px solid {{ $cc }};"></span>
    <span aria-hidden="true" style="{{ $cornerBase }} top: -1px; right: -1px; border-top: {{ $cs }}px solid {{ $cc }}; border-right: {{ $cs }}px solid {{ $cc }};"></span>
    <span aria-hidden="true" style="{{ $cornerBase }} bottom: -1px; left: -1px; border-bottom: {{ $cs }}px solid {{ $cc }}; border-left: {{ $cs }}px solid {{ $cc }};"></span>
    <span aria-hidden="true" style="{{ $cornerBase }} bottom: -1px; right: -1px; border-bottom: {{ $cs }}px solid {{ $cc }}; border-right: {{ $cs }}px solid {{ $cc }};"></span>
    {{ $slot }}
</div>

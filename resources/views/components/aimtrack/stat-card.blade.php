@props([
    'label' => '',
    'value' => '',
    'sub' => null,
    'subTone' => 'muted',
    'valueTone' => 'text',
])

@php
    $valueColor = match ($valueTone) {
        'accent' => 'var(--at-accent)',
        'warn'   => 'var(--at-warn)',
        'muted'  => 'var(--at-muted)',
        default  => 'var(--at-text)',
    };

    $subColor = match ($subTone) {
        'accent' => 'var(--at-accent)',
        'warn'   => 'var(--at-warn)',
        default  => 'var(--at-muted)',
    };
@endphp

<div
    {{ $attributes->merge([
        'class' => 'aimtrack-stat-card',
        'style' => 'padding: 16px; background: var(--at-panel); border: 1px solid var(--at-line); border-radius: var(--at-r-lg);',
    ]) }}
>
    <div class="at-label">{{ $label }}</div>
    <div style="font-family: var(--at-font-mono); font-size: var(--at-data-lg); font-weight: 600; margin-top: 6px; color: {{ $valueColor }}; letter-spacing: -0.01em; line-height: 1.1;">{{ $value }}</div>
    @if ($sub !== null)
        <div style="font-size: 11px; color: {{ $subColor }}; margin-top: 4px; display: flex; align-items: center; gap: 4px;">{{ $sub }}</div>
    @endif
</div>

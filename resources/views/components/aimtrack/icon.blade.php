@props([
    'name' => 'dot',
    'size' => 16,
    'color' => 'currentColor',
    'stroke' => 1.6,
])

@php
    $size = (float) $size;
    $stroke = (float) $stroke;

    $fmt = static fn (float $value): string => rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');

    /** @var array<string, string> $icons */
    $icons = [
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.2" fill="currentColor"/><line x1="12" y1="1" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="1" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="23" y2="12"/>',
        'crosshair' => '<circle cx="12" cy="12" r="9"/><line x1="12" y1="3" x2="12" y2="21"/><line x1="3" y1="12" x2="21" y2="12"/>',
        'weapon' => '<path d="M3 14h13l3-3h2v6h-3l-2 2h-3l-1 2H7l-1-2H3z"/><path d="M9 14V9h4v5"/>',
        'session' => '<rect x="3" y="5" width="18" height="16" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="16" y1="3" x2="16" y2="7"/>',
        'ai' => '<path d="M12 3l2 4 4 1-3 3 1 4-4-2-4 2 1-4-3-3 4-1z"/>',
        'spark' => '<polyline points="3 17 9 11 13 14 21 6"/><polyline points="14 6 21 6 21 13"/>',
        'export' => '<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.5" y2="16.5"/>',
        'bell' => '<path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 01-3.4 0"/>',
        'add' => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'arrow' => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
        'up' => '<polyline points="6 15 12 9 18 15"/>',
        'down' => '<polyline points="6 9 12 15 18 9"/>',
        'filter' => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
        'cal' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'chat' => '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>',
        'dot' => '<circle cx="12" cy="12" r="3" fill="currentColor"/>',
        'check' => '<polyline points="20 6 9 17 4 12"/>',
        'more' => '<circle cx="5" cy="12" r="1.4" fill="currentColor"/><circle cx="12" cy="12" r="1.4" fill="currentColor"/><circle cx="19" cy="12" r="1.4" fill="currentColor"/>',
    ];

    $paths = $icons[$name] ?? $icons['dot'];
@endphp

<svg
    {{ $attributes->merge([
        'aria-hidden' => 'true',
        'style' => "display: inline-block; vertical-align: middle; color: {$color};",
    ]) }}
    width="{{ $fmt($size) }}"
    height="{{ $fmt($size) }}"
    viewBox="0 0 24 24"
    fill="none"
    stroke="{{ $color }}"
    stroke-width="{{ $fmt($stroke) }}"
    stroke-linecap="round"
    stroke-linejoin="round"
>{!! $paths !!}</svg>

@props([
    'data' => null,
    'w' => 140,
    'h' => 36,
    'color' => null,
    'fill' => true,
    'strokeW' => 1.5,
])

@php
    $w = (float) $w;
    $h = (float) $h;
    $strokeW = (float) $strokeW;
    $color = $color ?? 'var(--at-accent)';

    /** @var list<int|float> $defaultData */
    $defaultData = [524, 531, 522, 540, 535, 542, 538, 547, 545, 552, 549, 547, 543, 550, 552, 547];
    $data = array_values(array_map('floatval', (array) ($data ?? $defaultData)));
    if (count($data) === 0) {
        $data = [0.0, 0.0];
    }

    $fmt = static fn (float $value): string => rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    $coord = static fn (float $value): string => number_format($value, 1, '.', '');

    $min = min($data);
    $max = max($data);
    $span = max(1, $max - $min);
    $denom = max(1, count($data) - 1);

    /** @var list<array{0: float, 1: float}> $pts */
    $pts = [];
    foreach ($data as $i => $v) {
        $x = ($i / $denom) * $w;
        $y = $h - (($v - $min) / $span) * ($h - 4) - 2;
        $pts[] = [$x, $y];
    }

    $segments = [];
    foreach ($pts as $i => $p) {
        $segments[] = ($i ? 'L' : 'M').$coord($p[0]).' '.$coord($p[1]);
    }
    $d = implode(' ', $segments);
    $area = $d.' L'.$fmt($w).' '.$fmt($h).' L0 '.$fmt($h).' Z';

    $gradId = 'sg-'.substr(md5($color.'|'.$fmt($w).'|'.$fmt($h).'|'.implode(',', $data)), 0, 8);

    $endPoint = $pts[count($pts) - 1];
@endphp

<svg
    {{ $attributes->merge([
        'aria-hidden' => 'true',
        'style' => 'display: block;',
    ]) }}
    width="{{ $fmt($w) }}"
    height="{{ $fmt($h) }}"
    viewBox="0 0 {{ $fmt($w) }} {{ $fmt($h) }}"
>
    @if ($fill)
        <defs>
            <linearGradient id="{{ $gradId }}" x1="0" x2="0" y1="0" y2="1">
                <stop offset="0%" stop-color="{{ $color }}" stop-opacity="0.35" />
                <stop offset="100%" stop-color="{{ $color }}" stop-opacity="0" />
            </linearGradient>
        </defs>
        <path d="{{ $area }}" fill="url(#{{ $gradId }})" />
    @endif
    <path d="{{ $d }}" fill="none" stroke="{{ $color }}" stroke-width="{{ $fmt($strokeW) }}" stroke-linecap="round" stroke-linejoin="round" />
    <circle cx="{{ $fmt($endPoint[0]) }}" cy="{{ $fmt($endPoint[1]) }}" r="2.5" fill="{{ $color }}" />
</svg>

@props([
    'size' => 200,
    'rings' => 10,
    'accent' => null,
    'dim' => null,
    'ringStroke' => 1,
    'hits' => [],
    'scoreLabels' => false,
])

@php
    $size = (float) $size;
    $rings = max(2, (int) $rings);
    $accent = $accent ?? 'var(--at-accent)';
    $dim = $dim ?? 'var(--at-text)';
    $ringStroke = (float) $ringStroke;

    $cx = $size / 2;
    $cy = $size / 2;
    $maxR = $size * 0.46;
    $hits = is_iterable($hits) ? (is_array($hits) ? $hits : iterator_to_array($hits)) : [];

    $fmt = static fn (float $value): string => rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
@endphp

<svg
    {{ $attributes->merge(['class' => 'aimtrack-target-rings', 'style' => 'display: block;']) }}
    width="{{ $fmt($size) }}"
    height="{{ $fmt($size) }}"
    viewBox="0 0 {{ $fmt($size) }} {{ $fmt($size) }}"
    role="img"
    aria-label="Hit-patroon"
>
    @for ($i = $rings; $i >= 1; $i--)
        @php
            $r = $maxR * ($i / $rings);
            $isCenter = $i <= 2;
            $stroke = $isCenter ? $accent : $dim;
            $opacity = $isCenter ? 0.7 : 0.32;
        @endphp
        <circle
            cx="{{ $fmt($cx) }}"
            cy="{{ $fmt($cy) }}"
            r="{{ $fmt($r) }}"
            fill="none"
            stroke="{{ $stroke }}"
            stroke-width="{{ $fmt($ringStroke) }}"
            opacity="{{ $fmt((float) $opacity) }}"
        />
    @endfor

    @if ($scoreLabels)
        @for ($i = 1; $i <= $rings; $i++)
            @php
                $r = $maxR * ($i / $rings) - ($maxR / $rings) * 0.5;
                $labelY = $cy + $r;
                $value = $rings - $i + 1;
            @endphp
            <text
                x="{{ $fmt($cx) }}"
                y="{{ $fmt($labelY + 3) }}"
                text-anchor="middle"
                fill="var(--at-muted)"
                style="font-family: var(--at-font-mono); font-size: 8px; letter-spacing: 0.06em;"
            >{{ $value }}</text>
        @endfor
    @endif

    @foreach ($hits as $hit)
        @php
            $hx = (float) ($hit['x'] ?? 0);
            $hy = (float) ($hit['y'] ?? 0);
            $hr = isset($hit['r']) ? (float) $hit['r'] : null;
            $px = $cx + ($hx * $maxR);
            $py = $cy + ($hy * $maxR);
            $color = ($hr !== null && $hr >= 10) ? $accent : 'var(--at-text)';
            $dotR = max(2.0, $size * 0.012);
        @endphp
        <circle
            cx="{{ $fmt($px) }}"
            cy="{{ $fmt($py) }}"
            r="{{ $fmt($dotR) }}"
            fill="{{ $color }}"
            opacity="0.85"
        />
    @endforeach
</svg>

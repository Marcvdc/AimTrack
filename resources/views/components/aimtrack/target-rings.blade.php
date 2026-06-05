@props([
    'size' => 200,
    'rings' => 8,
    'accent' => null,
    'dim' => null,
    'ringStroke' => 1,
    'hits' => [],
    'scoreLabels' => false,
    'hitScale' => 0.35,
])

@php
    // Faithful port of .ai/design-handoff/project/shared.jsx · TargetRings:
    // 8 concentric rings (10 = outer, emphasized; 3 = inner), dim fill on the
    // innermost rings, a center 10-ring dot, a dashed crosshair, score labels
    // for the top rings, and hit dots scaled by `hitScale` so a real shot
    // group reads as a tight cluster (the *0.35 damping the prototype uses)
    // instead of spreading to the rim.
    $size = (float) $size;
    $rings = max(2, (int) $rings);
    $accent = $accent ?? 'var(--at-accent)';
    $dim = $dim ?? 'var(--at-text)';
    $ringStroke = (float) $ringStroke;
    $hitScale = (float) $hitScale;

    $cx = $size / 2;
    $cy = $size / 2;
    $maxR = $size * 0.46;
    $highest = $rings + 2; // 8 rings → numbered 10..3
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
    @for ($i = 0; $i < $rings; $i++)
        @php
            $n = $highest - $i;
            $r = $maxR * (($rings - $i) / $rings);
            $hasFill = $n <= 4;
        @endphp
        <circle
            cx="{{ $fmt($cx) }}"
            cy="{{ $fmt($cy) }}"
            r="{{ $fmt($r) }}"
            fill="{{ $hasFill ? $dim : 'none' }}"
            fill-opacity="{{ $hasFill ? '0.06' : '0' }}"
            stroke="{{ $dim }}"
            stroke-opacity="{{ $fmt($n === $highest ? 0.55 : 0.22) }}"
            stroke-width="{{ $fmt($ringStroke) }}"
        />
    @endfor

    {{-- Center 10-ring dot --}}
    <circle
        cx="{{ $fmt($cx) }}"
        cy="{{ $fmt($cy) }}"
        r="{{ $fmt($maxR / $rings * 0.5) }}"
        fill="{{ $dim }}"
        fill-opacity="0.18"
    />

    {{-- Crosshair (dashed) --}}
    <line x1="{{ $fmt($cx - $maxR * 1.05) }}" y1="{{ $fmt($cy) }}" x2="{{ $fmt($cx + $maxR * 1.05) }}" y2="{{ $fmt($cy) }}"
        stroke="{{ $dim }}" stroke-opacity="0.18" stroke-width="{{ $fmt($ringStroke) }}" stroke-dasharray="2 4" />
    <line x1="{{ $fmt($cx) }}" y1="{{ $fmt($cy - $maxR * 1.05) }}" x2="{{ $fmt($cx) }}" y2="{{ $fmt($cy + $maxR * 1.05) }}"
        stroke="{{ $dim }}" stroke-opacity="0.18" stroke-width="{{ $fmt($ringStroke) }}" stroke-dasharray="2 4" />

    @if ($scoreLabels)
        @for ($i = 0; $i < $rings; $i++)
            @php $n = $highest - $i; @endphp
            @if ($n >= 7)
                @php $r = $maxR * (($rings - $i) / $rings); @endphp
                <text
                    x="{{ $fmt($cx + 4) }}"
                    y="{{ $fmt($cy - $r + 10) }}"
                    fill="{{ $dim }}"
                    fill-opacity="0.45"
                    style="font-family: var(--at-font-mono); font-size: 9px; letter-spacing: 0.04em;"
                >{{ $n }}</text>
            @endif
        @endfor
    @endif

    @foreach ($hits as $hit)
        @php
            $hx = (float) ($hit['x'] ?? 0);
            $hy = (float) ($hit['y'] ?? 0);
            $hr = isset($hit['r']) ? (float) $hit['r'] : null;
            $px = $cx + ($hx * $maxR * $hitScale);
            $py = $cy + ($hy * $maxR * $hitScale);
            $isTen = $hr !== null && $hr >= 9.5;
            $dotR = max(2.5, $size * 0.0145);
        @endphp
        <circle
            cx="{{ $fmt($px) }}"
            cy="{{ $fmt($py) }}"
            r="{{ $fmt($dotR) }}"
            fill="{{ $accent }}"
            fill-opacity="{{ $isTen ? '0.95' : '0.55' }}"
            @unless ($isTen) stroke="{{ $accent }}" stroke-width="0.5" @endunless
        />
        @if ($isTen)
            <circle
                cx="{{ $fmt($px) }}"
                cy="{{ $fmt($py) }}"
                r="{{ $fmt($dotR * 1.9) }}"
                fill="none"
                stroke="{{ $accent }}"
                stroke-opacity="0.35"
                stroke-width="{{ $fmt($ringStroke) }}"
            />
        @endif
    @endforeach
</svg>

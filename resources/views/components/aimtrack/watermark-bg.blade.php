@props([
    'size' => 220,
    'opacity' => 0.08,
    'top' => -40,
    'right' => -30,
    'left' => null,
    'bottom' => null,
    'center' => false,
    'color' => null,
    'dot' => true,
    'stroke' => 1,
])

@php
    $color = $color ?? 'var(--at-accent)';
    $size = (int) $size;
    $stroke = (float) $stroke;
@endphp

@if ($center)
    <div
        {{ $attributes->merge([
            'style' => "position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; z-index: 0; opacity: {$opacity};",
        ]) }}
    >
        <x-aimtrack.reticle :size="$size" :stroke="$stroke" :color="$color" :dot="$dot" opacity="1" />
    </div>
@else
    @php
        $styleParts = ['position: absolute'];
        $styleParts[] = $bottom !== null ? 'bottom: '.((int) $bottom).'px' : 'top: '.((int) $top).'px';
        $styleParts[] = $left !== null ? 'left: '.((int) $left).'px' : 'right: '.((int) $right).'px';
        $styleParts[] = 'pointer-events: none';
        $styleParts[] = 'z-index: 0';
        $style = implode('; ', $styleParts).';';
    @endphp
    <div {{ $attributes->merge(['style' => $style]) }}>
        <x-aimtrack.reticle :size="$size" :stroke="$stroke" :color="$color" :opacity="$opacity" :dot="$dot" />
    </div>
@endif

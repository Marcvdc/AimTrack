@props([
    'title' => '',
    'subtitle' => null,
    'reticleSize' => 180,
    'reticleOpacity' => 0.07,
    'reticleTop' => -30,
    'reticleRight' => -20,
    'showReticle' => true,
])

@php
    $reticleSize = (int) $reticleSize;
    $reticleOpacity = (float) $reticleOpacity;
    $reticleTop = (int) $reticleTop;
    $reticleRight = (int) $reticleRight;
@endphp

<div
    {{ $attributes->merge([
        'class' => 'aimtrack-page-header',
        'style' => 'position: relative; overflow: visible;',
    ]) }}
>
    @if ($showReticle)
        <x-aimtrack.watermark-bg
            :size="$reticleSize"
            :opacity="$reticleOpacity"
            :top="$reticleTop"
            :right="$reticleRight"
        />
    @endif

    <div class="aimtrack-page-header-row" style="position: relative; z-index: 1; display: flex; align-items: flex-start; gap: 16px;">
        <div style="flex: 1; min-width: 0;">
            <h1 style="font-family: var(--at-font-display); font-size: var(--at-text-heading); font-weight: 600; letter-spacing: -0.01em; margin: 0; color: var(--at-text);">{{ $title }}</h1>
            @if ($subtitle)
                <p style="color: var(--at-muted); font-size: var(--at-text-small); margin: 4px 0 0;">{{ $subtitle }}</p>
            @endif
        </div>

        @isset($actions)
            <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                {{ $actions }}
            </div>
        @endisset
    </div>
</div>

@props([
    'reticleOpacity' => 0.05,
    'reticleSize' => 460,
    'maxWidth' => '420px',
    'iconAccent' => false,
])

@php
    $reticleOpacity = (float) $reticleOpacity;
    $reticleSize = (int) $reticleSize;
    $iconBorderColor = $iconAccent ? 'var(--at-accent-25, rgba(100, 244, 179, 0.25))' : 'var(--at-line)';
@endphp

<div
    {{ $attributes->merge([
        'class' => 'at-empty-state',
        'style' => 'position: relative; display: flex; align-items: center; justify-content: center; padding: 32px 24px; min-height: 480px; width: 100%;',
    ]) }}
>
    {{-- T1 watermark — ambient reticle achter de content --}}
    <x-aimtrack.watermark-bg
        center
        :size="$reticleSize"
        :opacity="$reticleOpacity"
        :stroke="1"
        :dot="true"
        color="var(--at-accent)"
    />

    <div style="position: relative; z-index: 1; text-align: center; max-width: {{ $maxWidth }}; width: 100%;">
        @isset($icon)
            <div
                style="display: inline-flex; width: 64px; height: 64px; border-radius: 18px; background: var(--at-panel); border: 1px solid {{ $iconBorderColor }}; align-items: center; justify-content: center; color: var(--at-accent);"
            >
                {{ $icon }}
            </div>
        @endisset

        @isset($title)
            <h2
                style="font-family: var(--at-font-display); font-size: 22px; font-weight: 600; letter-spacing: -0.015em; color: var(--at-text); margin: 16px 0 0; line-height: 1.2;"
            >
                {{ $title }}
            </h2>
        @endisset

        @isset($description)
            <p
                style="font-family: var(--at-font-body); font-size: 14px; line-height: 1.5; color: var(--at-muted); margin: 10px auto 0; max-width: 360px;"
            >
                {{ $description }}
            </p>
        @endisset

        @isset($actions)
            <div style="margin-top: 22px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                {{ $actions }}
            </div>
        @endisset

        @isset($extra)
            <div style="margin-top: 28px;">
                {{ $extra }}
            </div>
        @endisset
    </div>
</div>

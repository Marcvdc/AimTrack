{{--
    Emits the AimTrack webfonts (Inter + JetBrains Mono) and inlines the
    Signal Mint design tokens for standalone pages (e.g. the marketing
    landing) that live outside the Filament panel.

    Single source of truth: resources/css/aimtrack-tokens.css — the exact
    same file injected into the panel <head> via
    AdminPanelProvider::aimTrackDesignAssets(). Keep the markup in sync with
    that method so the public site and the panel never drift.
--}}
@php
    $tokensPath = resource_path('css/aimtrack-tokens.css');
    $tokensCss = is_file($tokensPath) ? (string) file_get_contents($tokensPath) : '';
@endphp
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
<style id="aimtrack-tokens">
{!! $tokensCss !!}
</style>

{{--
    Emits the self-hosted AimTrack webfonts (Inter + JetBrains Mono, served
    from public/webfonts, #128: no requests to Google or Bunny) and inlines the
    Signal Mint design tokens for standalone pages (e.g. the marketing
    landing) that live outside the Filament panel.

    Single source of truth: resources/css/aimtrack-tokens.css — the exact
    same file injected into the panel <head> via
    AdminPanelProvider::aimTrackDesignAssets(). Keep the markup in sync with
    that method so the public site and the panel never drift.

    nginx caches css and woff2 for 30 days, so the stylesheet link carries a
    content hash: a changed font set reaches visitors on their next page load.
--}}
@php
    $tokensPath = resource_path('css/aimtrack-tokens.css');
    $tokensCss = is_file($tokensPath) ? (string) file_get_contents($tokensPath) : '';
    $fontsCssPath = public_path('webfonts/aimtrack-fonts.css');
    $fontsCssVersion = is_file($fontsCssPath) ? substr((string) md5_file($fontsCssPath), 0, 12) : null;
@endphp
<link rel="preload" href="{{ asset('webfonts/inter/inter-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ asset('webfonts/aimtrack-fonts.css') }}@if ($fontsCssVersion)?v={{ $fontsCssVersion }}@endif">
<style id="aimtrack-tokens">
{!! $tokensCss !!}
</style>

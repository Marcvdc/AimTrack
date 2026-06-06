<?php

use Illuminate\Support\Facades\Blade;

// Fase 3 brand primitives that are unique to the marketing landing.
// NB: target-rings + sparkline live on main (Fase 1) and are covered by
// tests/Unit/Aimtrack/*. This file covers the genuinely new primitives:
// icon, spark (marketing sparkline variant) and head-assets.

// ─── icon ──────────────────────────────────────────────────────────────────

test('icon renders a 24x24 stroke svg with sensible defaults', function (): void {
    $html = Blade::render('<x-aimtrack.icon />');

    expect($html)
        ->toContain('<svg')
        ->toContain('viewBox="0 0 24 24"')
        ->toContain('width="16"')
        ->toContain('height="16"')
        ->toContain('fill="none"')
        ->toContain('stroke="currentColor"')
        ->toContain('stroke-width="1.6"')
        ->toContain('stroke-linecap="round"')
        ->toContain('aria-hidden="true"');
});

test('icon defaults to the dot glyph when no name is given', function (): void {
    $html = Blade::render('<x-aimtrack.icon />');

    expect($html)->toContain('<circle cx="12" cy="12" r="3" fill="currentColor"/>');
});

test('icon falls back to the dot glyph for an unknown name', function (): void {
    $html = Blade::render('<x-aimtrack.icon name="does-not-exist" />');

    expect($html)->toContain('<circle cx="12" cy="12" r="3" fill="currentColor"/>');
});

// Locks the full ICONS map byte-for-byte against shared.jsx so a typo in any
// static glyph string is caught, even glyphs not yet used on the landing page.
test('icon renders each glyph path verbatim from the source set', function (string $name, string $needle): void {
    $html = Blade::render('<x-aimtrack.icon name="'.$name.'" />');

    expect($html)->toContain($needle);
})->with([
    'target' => ['target', '<circle cx="12" cy="12" r="5"/>'],
    'crosshair' => ['crosshair', '<line x1="12" y1="3" x2="12" y2="21"/>'],
    'weapon' => ['weapon', '<path d="M3 14h13l3-3h2v6h-3l-2 2h-3l-1 2H7l-1-2H3z"/>'],
    'session' => ['session', '<rect x="3" y="5" width="18" height="16" rx="2"/>'],
    'ai' => ['ai', '<path d="M12 3l2 4 4 1-3 3 1 4-4-2-4 2 1-4-3-3 4-1z"/>'],
    'spark' => ['spark', '<polyline points="3 17 9 11 13 14 21 6"/>'],
    'export' => ['export', '<polyline points="7 10 12 15 17 10"/>'],
    'search' => ['search', '<circle cx="11" cy="11" r="7"/>'],
    'bell' => ['bell', '<path d="M13.7 21a2 2 0 01-3.4 0"/>'],
    'add' => ['add', '<line x1="12" y1="5" x2="12" y2="19"/>'],
    'arrow' => ['arrow', '<polyline points="12 5 19 12 12 19"/>'],
    'up' => ['up', '<polyline points="6 15 12 9 18 15"/>'],
    'down' => ['down', '<polyline points="6 9 12 15 18 9"/>'],
    'filter' => ['filter', '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>'],
    'cal' => ['cal', '<rect x="3" y="4" width="18" height="18" rx="2"/>'],
    'shield' => ['shield', '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'],
    'chat' => ['chat', '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>'],
    'dot' => ['dot', '<circle cx="12" cy="12" r="3" fill="currentColor"/>'],
    'check' => ['check', '<polyline points="20 6 9 17 4 12"/>'],
    'more' => ['more', '<circle cx="5" cy="12" r="1.4" fill="currentColor"/>'],
]);

test('icon honours size, color and stroke props', function (): void {
    $html = Blade::render('<x-aimtrack.icon name="arrow" size="14" color="var(--at-accent)" stroke="2" />');

    expect($html)
        ->toContain('width="14"')
        ->toContain('height="14"')
        ->toContain('stroke="var(--at-accent)"')
        ->toContain('stroke-width="2"')
        ->toContain('color: var(--at-accent)');
});

// ─── spark ─────────────────────────────────────────────────────────────────

test('spark renders a sparkline svg with area fill by default', function (): void {
    $html = Blade::render('<x-aimtrack.spark />');

    expect($html)
        ->toContain('<svg')
        ->toContain('viewBox="0 0 140 36"')
        ->toContain('width="140"')
        ->toContain('height="36"')
        ->toContain('<linearGradient')
        ->toContain('stop-opacity="0.35"')
        ->toContain('stop-opacity="0"')
        ->toContain('url(#sg-')
        ->toContain('stroke="var(--at-accent)"')
        ->toContain('stroke-width="1.5"')
        ->toContain('r="2.5"')
        ->toContain('aria-hidden="true"');
});

test('spark closes the area path and starts the line with a move command', function (): void {
    $html = Blade::render('<x-aimtrack.spark />');

    expect($html)
        ->toContain('d="M')
        ->toContain(' Z"');
});

test('spark omits the gradient fill when fill is false', function (): void {
    $html = Blade::render('<x-aimtrack.spark :fill="false" />');

    expect($html)
        ->not->toContain('<linearGradient')
        ->not->toContain('<defs>')
        ->toContain('stroke="var(--at-accent)"');
});

test('spark normalises custom data, dimensions and colour', function (): void {
    $html = Blade::render('<x-aimtrack.spark :data="[1, 2, 3]" w="300" h="60" color="var(--at-warn)" stroke-w="2" />');

    expect($html)
        ->toContain('viewBox="0 0 300 60"')
        ->toContain('width="300"')
        ->toContain('height="60"')
        ->toContain('stroke="var(--at-warn)"')
        ->toContain('stroke-width="2"')
        ->toContain('stop-color="var(--at-warn)"')
        // min->bottom, max->top: first point at baseline, last at top of the band.
        ->toContain('M0.0 58.0')
        ->toContain('L300.0 2.0');
});

test('spark produces a gradient id free of invalid url() characters', function (): void {
    $html = Blade::render('<x-aimtrack.spark color="var(--at-accent)" />');

    expect($html)->toMatch('/url\(#sg-[a-z0-9]+\)/');
});

test('spark renders a flat baseline for a single data point', function (): void {
    $html = Blade::render('<x-aimtrack.spark :data="$data" />', ['data' => [5]]);

    // span falls back to 1, single point sits at h-2 = 34 on the default h=36.
    expect($html)
        ->toContain('<svg')
        ->toContain('d="M0.0 34.0');
});

test('spark survives an empty data set without erroring', function (): void {
    $html = Blade::render('<x-aimtrack.spark :data="$data" />', ['data' => []]);

    expect($html)
        ->toContain('<svg')
        ->toContain('d="M');
});

// ─── head-assets ─────────────────────────────────────────────────────────────

test('head-assets inlines the design tokens stylesheet', function (): void {
    $html = Blade::render('<x-aimtrack.head-assets />');

    expect($html)
        ->toContain('<style id="aimtrack-tokens">')
        ->toContain('--at-bg:')
        ->toContain('#040711')
        ->toContain('--at-accent:')
        ->toContain('#64f4b3');
});

test('head-assets links the Inter and JetBrains Mono webfonts', function (): void {
    $html = Blade::render('<x-aimtrack.head-assets />');

    expect($html)
        ->toContain('rel="preconnect"')
        ->toContain('fonts.googleapis.com')
        ->toContain('family=Inter')
        ->toContain('JetBrains+Mono');
});

test('head-assets emits the same token markup the panel injects', function (): void {
    $html = Blade::render('<x-aimtrack.head-assets />');

    $tokens = file_get_contents(resource_path('css/aimtrack-tokens.css'));

    expect($html)->toContain($tokens);
});

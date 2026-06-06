<?php

use Illuminate\Support\Facades\Blade;

// Fase 3 brand primitives that are genuinely new on this branch: icon + head-assets.
// target-rings + sparkline live on main (Fase 1) and are covered by tests/Unit/Aimtrack/*.
// (The marketing landing reuses main's <x-aimtrack.sparkline> for trend charts.)

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

test('head-assets links the Inter and JetBrains Mono webfonts with preconnect', function (): void {
    $html = Blade::render('<x-aimtrack.head-assets />');

    expect($html)
        ->toContain('<link rel="preconnect" href="https://fonts.googleapis.com">')
        ->toContain('<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>')
        ->toContain('family=Inter:wght@400;500;600;700')
        ->toContain('family=JetBrains+Mono:wght@400;500;600;700');
});

test('head-assets emits the full token stylesheet verbatim', function (): void {
    $html = Blade::render('<x-aimtrack.head-assets />');

    $tokens = file_get_contents(resource_path('css/aimtrack-tokens.css'));

    expect($html)->toContain($tokens);
});

// The Filament panel injects the exact same head markup via the same component
// (AdminPanelProvider::aimTrackDesignAssets renders <x-aimtrack.head-assets/>),
// so panel and public landing can never drift. The panel side is asserted by
// DesignTokensTest hitting /admin/login.

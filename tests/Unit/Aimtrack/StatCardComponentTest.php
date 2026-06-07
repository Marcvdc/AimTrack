<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

test('stat-card renders label and value with text colour by default', function (): void {
    $html = Blade::render('<x-aimtrack.stat-card label="SESSIES" value="14" />');

    expect($html)
        ->toContain('SESSIES')
        ->toContain('>14<')
        ->toContain('var(--at-text)')
        ->toContain('var(--at-panel)');
});

test('stat-card renders sub line with muted tone by default', function (): void {
    $html = Blade::render('<x-aimtrack.stat-card label="x" value="1" sub="laatste 30d" />');

    expect($html)
        ->toContain('laatste 30d')
        ->toContain('var(--at-muted)');
});

test('stat-card honours accent value-tone and sub-tone', function (): void {
    $html = Blade::render('<x-aimtrack.stat-card label="x" value="9" sub="+3" value-tone="accent" sub-tone="accent" />');

    expect(substr_count($html, 'var(--at-accent)'))->toBeGreaterThanOrEqual(2);
});

test('stat-card omits sub element when sub prop is null', function (): void {
    $html = Blade::render('<x-aimtrack.stat-card label="x" value="9" />');

    expect(substr_count($html, '<div'))->toBe(3);
});

test('stat-card supports warn tone for negative deltas', function (): void {
    $html = Blade::render('<x-aimtrack.stat-card label="x" value="9" sub="-3" sub-tone="warn" />');

    expect($html)->toContain('var(--at-warn)');
});

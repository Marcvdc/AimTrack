<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

test('sparkline renders polyline when given 2+ data points', function (): void {
    $html = Blade::render('<x-aimtrack.sparkline :data="$data" />', ['data' => [10, 15, 12, 18, 16]]);

    expect($html)
        ->toContain('<svg')
        ->toContain('<polyline')
        ->toContain('var(--at-accent)');
});

test('sparkline shows a dashed empty-state line when fewer than 2 points', function (): void {
    $html = Blade::render('<x-aimtrack.sparkline :data="[]" />');

    expect($html)
        ->toContain('<line')
        ->toContain('var(--at-line)')
        ->toContain('stroke-dasharray')
        ->not->toContain('<polyline');
});

test('sparkline honours width and height props', function (): void {
    $html = Blade::render('<x-aimtrack.sparkline :data="[1, 2]" width="500" height="100" />');

    expect($html)
        ->toContain('width="500"')
        ->toContain('height="100"')
        ->toContain('viewBox="0 0 500 100"');
});

test('sparkline renders a gradient area fill when fill prop is true', function (): void {
    $html = Blade::render('<x-aimtrack.sparkline :data="[1, 2, 3]" :fill="true" />');

    expect($html)
        ->toContain('<path')
        ->toContain('<linearGradient')
        ->toContain('stop-opacity="0.35"')
        ->toContain('fill="url(#atspark-');
});

test('sparkline omits area fill by default', function (): void {
    $html = Blade::render('<x-aimtrack.sparkline :data="[1, 2, 3]" />');

    expect($html)->not->toContain('<path');
});

test('sparkline marks the latest point with a terminal dot', function (): void {
    $html = Blade::render('<x-aimtrack.sparkline :data="[1, 2, 3]" />');

    expect($html)->toContain('<circle');
});

test('sparkline fluid prop emits a responsive width and drops the fixed pixel attributes (#104)', function (): void {
    $html = Blade::render('<x-aimtrack.sparkline :data="[1, 2, 3]" width="380" height="50" :fluid="true" />');

    expect($html)
        ->toContain('width: 100%')
        ->toContain('max-width: 380px')
        ->toContain('height: auto')
        ->toContain('viewBox="0 0 380 50"')
        ->not->toContain('width="380"')
        ->not->toContain('height="50"');
});

test('sparkline keeps fixed pixel dimensions when not fluid (#104)', function (): void {
    $html = Blade::render('<x-aimtrack.sparkline :data="[1, 2, 3]" width="380" height="50" />');

    expect($html)
        ->toContain('width="380"')
        ->toContain('height="50"')
        ->not->toContain('width: 100%');
});

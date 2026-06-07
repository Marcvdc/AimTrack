<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

test('target-rings renders 8 concentric rings plus a center dot by default', function (): void {
    $html = Blade::render('<x-aimtrack.target-rings />');

    expect($html)
        ->toContain('<svg')
        ->toContain('viewBox="0 0 200 200"')
        ->toContain('aria-label="Hit-patroon"');

    // 8 design rings + 1 center 10-ring dot.
    expect(substr_count($html, '<circle'))->toBe(9);
});

test('target-rings draws a dashed crosshair', function (): void {
    $html = Blade::render('<x-aimtrack.target-rings />');

    expect(substr_count($html, '<line'))->toBe(2)
        ->and($html)->toContain('stroke-dasharray="2 4"');
});

test('target-rings honours size prop', function (): void {
    $html = Blade::render('<x-aimtrack.target-rings size="320" />');

    expect($html)
        ->toContain('width="320"')
        ->toContain('viewBox="0 0 320 320"');
});

test('target-rings renders score labels for the top rings when scoreLabels is true', function (): void {
    $html = Blade::render('<x-aimtrack.target-rings score-labels="true" />');

    // Labels for rings 10, 9, 8, 7.
    expect(substr_count($html, '<text'))->toBe(4);
});

test('target-rings omits score labels by default', function (): void {
    $html = Blade::render('<x-aimtrack.target-rings />');

    expect($html)->not->toContain('<text');
});

test('target-rings plots hit dots and adds a halo for tens', function (): void {
    $hits = [
        ['x' => 0.1, 'y' => -0.2, 'r' => 9],  // not a ten → single dot
        ['x' => 0.0, 'y' => 0.0, 'r' => 10],  // ten → dot + halo
    ];
    $html = Blade::render('<x-aimtrack.target-rings :hits="$hits" />', ['hits' => $hits]);

    // 9 base circles + 1 (nine) + 2 (ten: dot + halo) = 12.
    expect(substr_count($html, '<circle'))->toBe(12);
});

test('target-rings honours custom ring count', function (): void {
    $html = Blade::render('<x-aimtrack.target-rings rings="5" />');

    // 5 rings + 1 center dot.
    expect(substr_count($html, '<circle'))->toBe(6);
});

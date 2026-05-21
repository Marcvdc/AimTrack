<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

test('target-rings renders 10 concentric rings by default', function (): void {
    $html = Blade::render('<x-aimtrack.target-rings />');

    expect($html)
        ->toContain('<svg')
        ->toContain('viewBox="0 0 200 200"')
        ->toContain('aria-label="Hit-patroon"');

    expect(substr_count($html, '<circle'))->toBe(10);
});

test('target-rings honours size prop', function (): void {
    $html = Blade::render('<x-aimtrack.target-rings size="320" />');

    expect($html)
        ->toContain('width="320"')
        ->toContain('viewBox="0 0 320 320"');
});

test('target-rings renders score labels when scoreLabels is true', function (): void {
    $html = Blade::render('<x-aimtrack.target-rings score-labels="true" />');

    expect(substr_count($html, '<text'))->toBe(10);
});

test('target-rings omits score labels by default', function (): void {
    $html = Blade::render('<x-aimtrack.target-rings />');

    expect($html)->not->toContain('<text');
});

test('target-rings plots hit dots when hits array is provided', function (): void {
    $hits = [
        ['x' => 0.1, 'y' => -0.2, 'r' => 9],
        ['x' => 0.0, 'y' => 0.0, 'r' => 10],
    ];
    $html = Blade::render('<x-aimtrack.target-rings :hits="$hits" />', ['hits' => $hits]);

    expect(substr_count($html, '<circle'))->toBe(12);
});

test('target-rings honours custom ring count', function (): void {
    $html = Blade::render('<x-aimtrack.target-rings rings="5" />');

    expect(substr_count($html, '<circle'))->toBe(5);
});

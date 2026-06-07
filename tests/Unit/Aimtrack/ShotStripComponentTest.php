<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

test('shot-strip renders bars for given scores', function (): void {
    $shots = [10, 9, 8, 10];
    $html = Blade::render('<x-aimtrack.shot-strip :shots="$shots" :total-slots="4" :show-legend="false" />', ['shots' => $shots]);

    expect(substr_count($html, '<div title='))->toBe(4);
});

test('shot-strip pads with placeholder slots up to totalSlots', function (): void {
    $shots = [10, 9];
    $html = Blade::render('<x-aimtrack.shot-strip :shots="$shots" :total-slots="6" :show-legend="false" />', ['shots' => $shots]);

    expect(substr_count($html, '<div title='))->toBe(2);
    expect($html)->toContain('var(--at-line)');
});

test('shot-strip highlights tens with accent', function (): void {
    $shots = [10, 8, 10];
    $html = Blade::render('<x-aimtrack.shot-strip :shots="$shots" :total-slots="3" :show-legend="false" />', ['shots' => $shots]);

    expect(substr_count($html, 'background: var(--at-accent)'))->toBeGreaterThanOrEqual(2);
});

test('shot-strip marks dip range with warn colour', function (): void {
    $shots = [10, 9, 7, 7, 8];
    $html = Blade::render('<x-aimtrack.shot-strip :shots="$shots" :total-slots="5" :dip-range="[2, 3]" :show-legend="false" />', ['shots' => $shots]);

    expect($html)
        ->toContain('var(--at-warn)');
});

test('shot-strip legend renders by default and can be hidden', function (): void {
    $shots = [10];
    $on = Blade::render('<x-aimtrack.shot-strip :shots="$shots" />', ['shots' => $shots]);
    $off = Blade::render('<x-aimtrack.shot-strip :shots="$shots" :show-legend="false" />', ['shots' => $shots]);

    expect($on)->toContain('10+ ringen');
    expect($off)->not->toContain('10+ ringen');
});

test('shot-strip accepts shots as array of associative entries with score key', function (): void {
    $shots = [['score' => 10], ['score' => 9]];
    $html = Blade::render('<x-aimtrack.shot-strip :shots="$shots" :total-slots="2" :show-legend="false" />', ['shots' => $shots]);

    expect(substr_count($html, '<div title='))->toBe(2);
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

test('series-card renders one cell per serie with score', function (): void {
    $series = [91, 88, 95, 87, 90, 96];
    $html = Blade::render('<x-aimtrack.series-card :series="$series" />', ['series' => $series]);

    expect($html)
        ->toContain('SERIE 1')
        ->toContain('SERIE 6')
        ->toContain('>91.0<')
        ->toContain('>96.0<')
        ->toContain('6 × 10 schoten');
});

test('series-card uses accent colour for series at or above good threshold', function (): void {
    $series = [91, 95];
    $html = Blade::render('<x-aimtrack.series-card :series="$series" good-threshold="95" />', ['series' => $series]);

    expect($html)->toContain('var(--at-accent)');
});

test('series-card shows dash when series is empty', function (): void {
    $html = Blade::render('<x-aimtrack.series-card :series="[]" />');

    expect($html)
        ->toContain('—')
        ->not->toContain('SERIE 1');
});

test('series-card honours custom shots-per-serie label', function (): void {
    $series = [49, 50];
    $html = Blade::render('<x-aimtrack.series-card :series="$series" :shots-per-serie="5" />', ['series' => $series]);

    expect($html)->toContain('2 × 5 schoten');
});

test('series-card computes default sub line as total of series', function (): void {
    $series = [90, 91, 89];
    $html = Blade::render('<x-aimtrack.series-card :series="$series" />', ['series' => $series]);

    expect($html)->toContain('tot 270.0');
});

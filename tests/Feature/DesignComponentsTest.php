<?php

use Illuminate\Support\Facades\Blade;

test('reticle component renders svg with default size and circle', function (): void {
    $html = Blade::render('<x-aimtrack.reticle />');

    expect($html)
        ->toContain('<svg')
        ->toContain('viewBox="0 0 200 200"')
        ->toContain('width="200"')
        ->toContain('height="200"')
        ->toContain('<circle')
        ->toContain('opacity: 1');
});

test('reticle component honours size, color and opacity props', function (): void {
    $html = Blade::render('<x-aimtrack.reticle size="80" color="#64f4b3" opacity="0.5" />');

    expect($html)
        ->toContain('width="80"')
        ->toContain('viewBox="0 0 80 80"')
        ->toContain('stroke="#64f4b3"')
        ->toContain('opacity: 0.5');
});

test('reticle component renders centre dot when dot prop is true', function (): void {
    $html = Blade::render('<x-aimtrack.reticle :dot="true" />');

    expect(substr_count($html, '<circle'))->toBe(2);
});

test('reticle component omits centre dot by default', function (): void {
    $html = Blade::render('<x-aimtrack.reticle />');

    expect(substr_count($html, '<circle'))->toBe(1);
});

test('reticle component draws four crosshair ticks', function (): void {
    $html = Blade::render('<x-aimtrack.reticle />');

    expect(substr_count($html, '<line'))->toBe(4);
});

test('at-mark component renders five-line monogram svg', function (): void {
    $html = Blade::render('<x-aimtrack.at-mark />');

    expect($html)
        ->toContain('<svg')
        ->toContain('viewBox="0 0 100 100"')
        ->toContain('width="80"')
        ->toContain('stroke-width="9"');

    expect(substr_count($html, '<line'))->toBe(5);
});

test('at-mark component honours size and color props', function (): void {
    $html = Blade::render('<x-aimtrack.at-mark size="18" color="#94a3c5" />');

    expect($html)
        ->toContain('width="18"')
        ->toContain('stroke="#94a3c5"');
});

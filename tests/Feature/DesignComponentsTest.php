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

test('watermark-bg defaults to absolute positioning with top/right offsets', function (): void {
    $html = Blade::render('<x-aimtrack.watermark-bg />');

    expect($html)
        ->toContain('position: absolute')
        ->toContain('top: -40px')
        ->toContain('right: -30px')
        ->toContain('pointer-events: none')
        ->toContain('z-index: 0')
        ->toContain('var(--at-accent)');
});

test('watermark-bg honours center prop and centers the reticle', function (): void {
    $html = Blade::render('<x-aimtrack.watermark-bg :center="true" opacity="0.12" />');

    expect($html)
        ->toContain('inset: 0')
        ->toContain('justify-content: center')
        ->toContain('opacity: 0.12')
        ->not->toContain('top: -40px');
});

test('watermark-bg switches to bottom/left when those are passed', function (): void {
    $html = Blade::render('<x-aimtrack.watermark-bg bottom="20" left="10" />');

    expect($html)
        ->toContain('bottom: 20px')
        ->toContain('left: 10px')
        ->not->toContain('top: -40px')
        ->not->toContain('right: -30px');
});

test('bracket-frame draws four corner brackets around its slot', function (): void {
    $html = Blade::render('<x-aimtrack.bracket-frame>AI inzicht</x-aimtrack.bracket-frame>');

    expect($html)
        ->toContain('AI inzicht')
        ->toContain('position: relative')
        ->toContain('var(--at-panel)')
        ->toContain('var(--at-line)')
        ->toContain('border-top: 1.5px solid var(--at-accent)')
        ->toContain('border-bottom: 1.5px solid var(--at-accent)')
        ->toContain('border-left: 1.5px solid var(--at-accent)')
        ->toContain('border-right: 1.5px solid var(--at-accent)');

    expect(substr_count($html, 'aria-hidden="true"'))->toBe(4);
});

test('bracket-frame honours corner size, stroke, padding and rounded props', function (): void {
    $html = Blade::render('<x-aimtrack.bracket-frame corner-size="20" corner-stroke="2" rounded="12" padding="24">x</x-aimtrack.bracket-frame>');

    expect($html)
        ->toContain('width: 20px')
        ->toContain('height: 20px')
        ->toContain('border-top: 2px solid var(--at-accent)')
        ->toContain('border-radius: 12px')
        ->toContain('padding: 24px');
});

test('bracket-frame can disable panel background and border', function (): void {
    $html = Blade::render('<x-aimtrack.bracket-frame :panel="false" :bordered="false">y</x-aimtrack.bracket-frame>');

    expect($html)
        ->toContain('background: transparent')
        ->toContain('border: none')
        ->not->toContain('var(--at-panel)');
});

test('ring-medaillon renders reticle, label, value and sub readout', function (): void {
    $html = Blade::render('<x-aimtrack.ring-medaillon />');

    expect($html)
        ->toContain('SCORE')
        ->toContain('>547<')
        ->toContain('>/600<')
        ->toContain('max-width: 200px')
        ->toContain('aspect-ratio: 1')
        ->toContain('<svg');
});

test('ring-medaillon honours custom size, label, value and sub', function (): void {
    $html = Blade::render('<x-aimtrack.ring-medaillon size="130" label="GROEP" value="22" sub="mm" />');

    expect($html)
        ->toContain('GROEP')
        ->toContain('>22<')
        ->toContain('>mm<')
        ->toContain('max-width: 130px');
});

test('fluid svg primitives carry their shared class hooks (#104)', function (): void {
    expect(Blade::render('<x-aimtrack.reticle :size="100" />'))->toContain('aimtrack-reticle');
    expect(Blade::render('<x-aimtrack.at-mark :size="20" />'))->toContain('aimtrack-at-mark');
    expect(Blade::render('<x-aimtrack.icon name="target" :size="16" />'))->toContain('aimtrack-icon');
});

test('monogram-stamp renders solid stamp by default with at-mark and label', function (): void {
    $html = Blade::render('<x-aimtrack.monogram-stamp label="WM-4 OK" />');

    expect($html)
        ->toContain('WM-4 OK')
        ->toContain('var(--at-accent)')
        ->toContain('var(--at-cta-text)')
        ->toContain('<svg')
        ->toContain('display: inline-flex');
});

test('monogram-stamp outline variant uses transparent background and bordered ring', function (): void {
    $html = Blade::render('<x-aimtrack.monogram-stamp label="VERIFIED" variant="outline" />');

    expect($html)
        ->toContain('background: transparent')
        ->toContain('color-mix')
        ->toContain('VERIFIED');
});

test('monogram-stamp absolute positions itself when corner is top-right', function (): void {
    $html = Blade::render('<x-aimtrack.monogram-stamp corner="top-right" />');

    expect($html)
        ->toContain('position: absolute')
        ->toContain('top: -10px')
        ->toContain('right: 16px');
});

test('wordmark renders Aim/Track text with logo mask and accent split', function (): void {
    $html = Blade::render('<x-aimtrack.wordmark />');

    expect($html)
        ->toContain('aria-label="AimTrack"')
        ->toContain('Aim')
        ->toContain('Track</span>')
        ->toContain('var(--at-text)')
        ->toContain('var(--at-accent)')
        ->toContain('aimtrack-logo.svg')
        ->toContain('mask:');
});

test('wordmark scales gap and text-size with the size prop', function (): void {
    $html = Blade::render('<x-aimtrack.wordmark size="40" />');

    expect($html)
        ->toContain('width: 40px')
        ->toContain('height: 40px')
        ->toContain('gap: 12.8px');
});

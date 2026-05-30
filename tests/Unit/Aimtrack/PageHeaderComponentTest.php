<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

test('page-header renders title and subtitle with default reticle watermark', function (): void {
    $html = Blade::render('<x-aimtrack.page-header title="Sessies" subtitle="247 totaal" />');

    expect($html)
        ->toContain('>Sessies</h1>')
        ->toContain('>247 totaal</p>')
        ->toContain('position: absolute')
        ->toContain('var(--at-accent)');
});

test('page-header can hide the reticle watermark', function (): void {
    $html = Blade::render('<x-aimtrack.page-header title="x" :show-reticle="false" />');

    expect($html)
        ->toContain('>x</h1>')
        ->not->toContain('aria-hidden="true"');
});

test('page-header honours reticle size, opacity and position props', function (): void {
    $html = Blade::render('<x-aimtrack.page-header title="x" reticle-size="220" reticle-opacity="0.12" reticle-top="-50" reticle-right="-10" />');

    expect($html)
        ->toContain('top: -50px')
        ->toContain('right: -10px')
        ->toContain('opacity: 0.12');
});

test('page-header renders actions slot when provided', function (): void {
    $html = Blade::render('<x-aimtrack.page-header title="x"><x-slot:actions><button>Filter</button></x-slot:actions></x-aimtrack.page-header>');

    expect($html)
        ->toContain('<button>Filter</button>')
        ->toContain('flex-shrink: 0');
});

test('page-header omits subtitle paragraph when subtitle is null', function (): void {
    $html = Blade::render('<x-aimtrack.page-header title="x" />');

    expect(substr_count($html, '<p '))->toBe(0);
});

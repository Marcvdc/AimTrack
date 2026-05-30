<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

test('empty-state renders centered container with reticle background', function (): void {
    $html = Blade::render('<x-aimtrack.empty-state />');

    expect($html)
        ->toContain('at-empty-state')
        ->toContain('position: relative')
        ->toContain('text-align: center');
});

test('empty-state injects watermark-bg with default reticle size and opacity', function (): void {
    $html = Blade::render('<x-aimtrack.empty-state />');

    expect($html)
        ->toContain('<svg')
        ->toContain('width="460"')
        ->toContain('opacity: 0.05')
        ->toContain('var(--at-accent)');
});

test('empty-state honours custom reticle size, opacity and max width', function (): void {
    $html = Blade::render('<x-aimtrack.empty-state reticle-size="320" reticle-opacity="0.08" max-width="520px" />');

    expect($html)
        ->toContain('width="320"')
        ->toContain('opacity: 0.08')
        ->toContain('max-width: 520px');
});

test('empty-state renders title slot inside h2 with display font', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-aimtrack.empty-state>
            <x-slot:title>Nog geen sessies gelogd</x-slot:title>
        </x-aimtrack.empty-state>
    BLADE);

    expect($html)
        ->toContain('<h2')
        ->toContain('var(--at-font-display)')
        ->toContain('Nog geen sessies gelogd');
});

test('empty-state renders description, actions and extra slots', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-aimtrack.empty-state>
            <x-slot:description>Log je eerste training.</x-slot:description>
            <x-slot:actions><button>Start</button></x-slot:actions>
            <x-slot:extra><div class="tip">tip</div></x-slot:extra>
        </x-aimtrack.empty-state>
    BLADE);

    expect($html)
        ->toContain('Log je eerste training.')
        ->toContain('<button>Start</button>')
        ->toContain('<div class="tip">tip</div>');
});

test('empty-state omits icon block when slot is empty', function (): void {
    $html = Blade::render('<x-aimtrack.empty-state />');

    expect($html)->not->toContain('border-radius: 18px');
});

test('empty-state renders icon slot inside accent-bordered square when iconAccent prop set', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-aimtrack.empty-state :icon-accent="true">
            <x-slot:icon><svg class="ai-icon"></svg></x-slot:icon>
        </x-aimtrack.empty-state>
    BLADE);

    expect($html)
        ->toContain('<svg class="ai-icon"></svg>')
        ->toContain('border-radius: 18px')
        ->toContain('var(--at-accent-25')
        ->toContain('var(--at-panel)');
});

test('empty-state uses line border for icon when iconAccent prop is false', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-aimtrack.empty-state>
            <x-slot:icon><svg></svg></x-slot:icon>
        </x-aimtrack.empty-state>
    BLADE);

    expect($html)
        ->toContain('border: 1px solid var(--at-line)')
        ->not->toContain('var(--at-accent-25');
});

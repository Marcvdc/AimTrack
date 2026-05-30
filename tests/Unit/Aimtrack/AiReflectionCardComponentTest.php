<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

test('ai-reflection-card wraps content in bracket-frame with accent corners', function (): void {
    $html = Blade::render('<x-aimtrack.ai-reflection-card />');

    expect($html)
        ->toContain('AI-reflectie')
        ->toContain('var(--at-accent)');

    expect(substr_count($html, 'aria-hidden="true"'))->toBe(4);
});

test('ai-reflection-card renders summary, sterk, verbeter, volgende rows', function (): void {
    $reflection = (object) [
        'summary' => 'Sterke openingsserie met 5× tien op rij.',
        'positives' => ['Openingsritme', 'Polsstabiliteit'],
        'improvements' => ['Adempauze rond schot 30'],
        'next_focus' => '2× 10 min droogoefenen',
    ];
    $html = Blade::render('<x-aimtrack.ai-reflection-card :reflection="$reflection" session-id="S-0247" />', ['reflection' => $reflection]);

    expect($html)
        ->toContain('S-0247')
        ->toContain('Sterke openingsserie')
        ->toContain('STERK')
        ->toContain('Openingsritme · Polsstabiliteit')
        ->toContain('VERBETER')
        ->toContain('Adempauze rond schot 30')
        ->toContain('VOLGENDE')
        ->toContain('2× 10 min droogoefenen');
});

test('ai-reflection-card shows dash for missing reflection fields', function (): void {
    $reflection = (object) ['summary' => null, 'positives' => null, 'improvements' => null, 'next_focus' => null];
    $html = Blade::render('<x-aimtrack.ai-reflection-card :reflection="$reflection" />', ['reflection' => $reflection]);

    expect(substr_count($html, '—'))->toBeGreaterThanOrEqual(4);
});

test('ai-reflection-card omits sterk/verbeter/volgende grid in compact mode', function (): void {
    $reflection = (object) ['summary' => 'Compact view', 'positives' => 'x', 'improvements' => 'y', 'next_focus' => 'z'];
    $html = Blade::render('<x-aimtrack.ai-reflection-card :reflection="$reflection" :compact="true" />', ['reflection' => $reflection]);

    expect($html)
        ->toContain('Compact view')
        ->not->toContain('STERK')
        ->not->toContain('VERBETER');
});

test('ai-reflection-card renders actions slot when provided', function (): void {
    $html = Blade::render('<x-aimtrack.ai-reflection-card><x-slot:actions><button>Vraag coach</button></x-slot:actions></x-aimtrack.ai-reflection-card>');

    expect($html)->toContain('<button>Vraag coach</button>');
});

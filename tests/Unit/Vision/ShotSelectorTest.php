<?php

declare(strict_types=1);

use App\Services\Vision\DetectedShot;
use App\Services\Vision\ShotSelector;

function candidate(float $confidence, int $ring = 9): DetectedShot
{
    return new DetectedShot(0.1, 0.1, $ring, $confidence, 'hole');
}

test('schoten onder de zekerheidsdrempel vallen af en worden apart geteld', function (): void {
    $selection = (new ShotSelector(0.25))->select([
        candidate(0.9),
        candidate(0.24),
        candidate(0.25),
    ], null);

    expect($selection->kept)->toHaveCount(2)
        ->and($selection->droppedLowConfidence)->toHaveCount(1)
        ->and($selection->droppedOverCount)->toBe([]);
});

test('de overtollige, minst zekere schoten vallen apart af bij een bekend aantal', function (): void {
    $selection = (new ShotSelector(0.25))->select([
        candidate(0.5, 7),
        candidate(0.95, 10),
        candidate(0.8, 9),
    ], 2);

    expect(array_map(fn (DetectedShot $shot): int => $shot->ring, $selection->kept))->toBe([10, 9])
        ->and($selection->droppedOverCount)->toHaveCount(1)
        ->and($selection->droppedOverCount[0]->ring)->toBe(7);
});

test('er wordt nooit aangevuld tot het verwachte aantal', function (): void {
    $selection = (new ShotSelector(0.25))->select([candidate(0.9)], 5);

    expect($selection->kept)->toHaveCount(1);
});

test('zonder verwacht aantal blijft alles boven de drempel staan', function (): void {
    $selection = (new ShotSelector(0.25))->select([
        candidate(0.9),
        candidate(0.8),
        candidate(0.7),
    ], null);

    expect($selection->kept)->toHaveCount(3);
});

test('een bord met alleen plakkers levert nul schoten op', function (): void {
    $selection = (new ShotSelector(0.25))->select([], 5);

    expect($selection->kept)->toBe([])
        ->and($selection->droppedLowConfidence)->toBe([]);
});

test('de drempel van 0.25 houdt de schoten die bij 0.4 wegvielen', function (): void {
    // Op de overlays van ronde 1 waren de markers die bij 0.4 afvielen (0.30 en 0.35)
    // zichtbaar echte gaten. Die moeten er nu doorheen komen.
    $selection = (new ShotSelector(0.25))->select([candidate(0.30), candidate(0.35)], null);

    expect($selection->kept)->toHaveCount(2)
        ->and($selection->droppedLowConfidence)->toBe([]);
});

test('de weggevallen zekerheden zijn terug te vinden in het overzicht', function (): void {
    $overzicht = (new ShotSelector(0.25))->select([candidate(0.9), candidate(0.1)], null)->toArray();

    expect($overzicht['kept'])->toBe(1)
        ->and($overzicht['dropped_low_confidence'])->toBe(1)
        ->and($overzicht['low_confidence_values'])->toBe([0.1]);
});

test('een gat dat het model als mis las komt niet als schot op het bord', function (): void {
    /*
     * Het echte geval uit de eerste upload: het model wees een gat aan op 1,19
     * ring1-stralen, dus buiten de kaartrand, en las er ring 0 bij. Zonder deze
     * zeef rekende het bord daar een score van 5 uit de positie.
     */
    $mis = new DetectedShot(0.84, -0.84, 0, 0.4, 'hole');

    $selection = (new ShotSelector(0.25))->select([candidate(0.9), $mis], null);

    expect($selection->kept)->toHaveCount(1)
        ->and($selection->droppedOffTarget)->toHaveCount(1)
        ->and($selection->droppedOffTarget[0]->ring)->toBe(0);
});

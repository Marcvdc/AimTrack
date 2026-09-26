<?php

declare(strict_types=1);

use App\Services\Vision\DetectedShot;
use App\Services\Vision\TargetFrame;

/**
 * De omrekening van de modelconventie (midden = 0, rand ring 1 = 1) naar de
 * bordconventie (midden = 0.5, rand ring 1 = 0.5 straal) is eerder fout gegaan
 * doordat de bordverhouding dubbel werd meegerekend. Daarom staat hij hier vast.
 */
test('het midden van de roos valt op het midden van het bord', function (): void {
    expect((new DetectedShot(0.0, 0.0, 10, 1.0, 'hole'))->toBoardCoordinates())
        ->toBe(['x' => 0.5, 'y' => 0.5]);
});

test('de rand van ring 1 valt op de rand van het bord', function (): void {
    expect((new DetectedShot(1.0, 0.0, 1, 0.9, 'hole'))->toBoardCoordinates())
        ->toBe(['x' => 1.0, 'y' => 0.5]);

    expect((new DetectedShot(0.0, -1.0, 1, 0.9, 'hole'))->toBoardCoordinates())
        ->toBe(['x' => 0.5, 'y' => 0.0]);
});

test('een schot halverwege komt op een kwart van het bord uit', function (): void {
    expect((new DetectedShot(-0.5, 0.5, 5, 0.9, 'hole'))->toBoardCoordinates())
        ->toBe(['x' => 0.25, 'y' => 0.75]);
});

test('de afstand tot het midden wordt in ring1-stralen gemeten', function (): void {
    expect(round((new DetectedShot(0.3, 0.4, 7, 0.9, 'hole'))->distanceNormalized(), 6))->toBe(0.5);
});

test('een genormaliseerde positie is terug te rekenen naar pixels', function (): void {
    $frame = new TargetFrame(400.0, 350.0, 200.0, 1);

    expect($frame->toPixels(0.0, 0.0))->toBe(['x' => 400.0, 'y' => 350.0])
        ->and($frame->toPixels(1.0, 0.0))->toBe(['x' => 600.0, 'y' => 350.0])
        ->and($frame->toPixels(0.0, -0.5))->toBe(['x' => 400.0, 'y' => 250.0]);
});

test('een roos die bij ring 1 begint heeft geen omrekening nodig', function (): void {
    expect((new TargetFrame(0.0, 0.0, 100.0, 1))->ring1Scale())->toBe(1.0);
});

test('een roos waarvan de buitenste ring een 6 is wordt gehalveerd', function (): void {
    // Zoals IMG_6462: de buitenste gedrukte ring is een 6, dus de rand daarvan ligt
    // op de helft van de ring1-straal. Zonder deze factor belandt elk schot twee keer
    // zo ver van het midden op het schotbord.
    expect((new TargetFrame(0.0, 0.0, 100.0, 6))->ring1Scale())->toBe(0.5);
});

test('de bordpositie schaalt mee met de buitenste ring', function (): void {
    $schot = new DetectedShot(1.0, 0.0, 6, 0.9, 'hole');

    expect($schot->toBoardCoordinates(1.0))->toBe(['x' => 1.0, 'y' => 0.5])
        ->and($schot->toBoardCoordinates(0.5))->toBe(['x' => 0.75, 'y' => 0.5]);
});

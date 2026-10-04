<?php

declare(strict_types=1);

use App\Services\Vision\DetectedShot;
use App\Services\Vision\DetectionMetrics;

function metrics(): DetectionMetrics
{
    return new DetectionMetrics;
}

function shotAt(float $x, float $y, int $ring = 9, float $confidence = 0.9): DetectedShot
{
    return new DetectedShot($x, $y, $ring, $confidence, 'hole');
}

test('een perfecte beurt levert geen afwijking op', function (): void {
    $result = metrics()->compareTurn([9, 8, 7], [7, 9, 8]);

    expect($result['count_correct'])->toBeTrue()
        ->and($result['count_delta'])->toBe(0)
        ->and($result['ring_accuracy'])->toBe(1.0)
        ->and($result['ring_mae'])->toBe(0.0);
});

test('overtelling geeft een positieve count_delta', function (): void {
    $result = metrics()->compareTurn([9, 8], [9, 8, 6]);

    expect($result['count_delta'])->toBe(1)
        ->and($result['count_correct'])->toBeFalse();
});

test('ondertelling geeft een negatieve count_delta', function (): void {
    $result = metrics()->compareTurn([9, 8, 7], [9]);

    expect($result['count_delta'])->toBe(-2);
});

test('ringafwijking wordt als gemiddelde absolute fout gemeten', function (): void {
    $result = metrics()->compareTurn([10, 8], [9, 7]);

    expect($result['ring_mae'])->toBe(1.0)
        ->and($result['ring_accuracy'])->toBe(0.0);
});

test('een leeg bord zonder detecties telt als goed', function (): void {
    $result = metrics()->compareTurn([], []);

    expect($result['count_correct'])->toBeTrue()
        ->and($result['ring_accuracy'])->toBe(1.0)
        ->and($result['ring_mae'])->toBe(0.0);
});

test('detecties op een leeg bord zijn fout en niet te middelen', function (): void {
    $result = metrics()->compareTurn([], [7]);

    expect($result['ring_accuracy'])->toBe(0.0)
        ->and($result['ring_mae'])->toBeNull();
});

test('niets gevonden terwijl er wel geschoten is levert geen ring-MAE op', function (): void {
    $result = metrics()->compareTurn([9, 9], []);

    expect($result['count_correct'])->toBeFalse()
        ->and($result['ring_mae'])->toBeNull();
});

test('aggregatie telt over- en ondertelling apart', function (): void {
    $aggregate = metrics()->aggregate([
        ['count_correct' => true, 'count_delta' => 0, 'ring_accuracy' => 1.0, 'ring_mae' => 0.0],
        ['count_correct' => false, 'count_delta' => 2, 'ring_accuracy' => 0.5, 'ring_mae' => 1.0],
        ['count_correct' => false, 'count_delta' => -1, 'ring_accuracy' => 0.0, 'ring_mae' => null],
    ]);

    expect($aggregate['turns'])->toBe(3)
        ->and($aggregate['over_count_turns'])->toBe(1)
        ->and($aggregate['under_count_turns'])->toBe(1)
        ->and(round($aggregate['count_accuracy'], 3))->toBe(0.333)
        ->and($aggregate['mean_ring_mae'])->toBe(0.5);
});

test('aggregatie van een lege set meldt nul beurten', function (): void {
    expect(metrics()->aggregate([]))->toBe(['turns' => 0]);
});

test('spreiding blijft leeg bij een enkele run', function (): void {
    $stability = metrics()->positionalStability([[shotAt(0.1, 0.1)]]);

    expect($stability['runs'])->toBe(1)
        ->and($stability['mean_spread'])->toBeNull();
});

test('spreiding meet de afstand tot het dichtstbijzijnde schot in een volgende run', function (): void {
    $stability = metrics()->positionalStability([
        [shotAt(0.0, 0.0), shotAt(0.5, 0.0)],
        [shotAt(0.0, 0.1), shotAt(0.5, 0.0)],
    ]);

    expect(round($stability['mean_spread'], 4))->toBe(0.05)
        ->and(round($stability['max_spread'], 4))->toBe(0.1)
        ->and($stability['count_spread'])->toBe(0);
});

test('spreiding meldt een verschil in aantal tussen runs', function (): void {
    $stability = metrics()->positionalStability([
        [shotAt(0.0, 0.0), shotAt(0.5, 0.0)],
        [shotAt(0.0, 0.0)],
    ]);

    expect($stability['count_spread'])->toBe(1);
});

test('de ring uit de afstand volgt de gelijke ringstappen', function (): void {
    expect(metrics()->ringFromDistance(0.0))->toBe(10)
        ->and(metrics()->ringFromDistance(0.05))->toBe(10)
        ->and(metrics()->ringFromDistance(0.15))->toBe(9)
        ->and(metrics()->ringFromDistance(0.95))->toBe(1)
        ->and(metrics()->ringFromDistance(1.0))->toBe(1)
        ->and(metrics()->ringFromDistance(1.4))->toBe(0);
});

test('een gelezen ring die bij de positie past telt als overeenkomst', function (): void {
    $agreement = metrics()->ringPositionAgreement([
        shotAt(0.05, 0.0, 10),
        shotAt(0.0, 0.85, 2),
    ]);

    expect($agreement['shots'])->toBe(2)
        ->and($agreement['agree'])->toBe(2)
        ->and($agreement['mean_abs_delta'])->toBe(0.0)
        ->and($agreement['max_abs_delta'])->toBe(0);
});

test('een te grote geschatte ring1-straal trekt de posities naar het midden', function (): void {
    // Het model leest ring 6, maar plaatst het schot op 0.15 van het midden,
    // wat bij gelijke ringstappen ring 9 zou zijn: drie ringen verschil.
    $agreement = metrics()->ringPositionAgreement([shotAt(0.15, 0.0, 6)]);

    expect($agreement['agree'])->toBe(0)
        ->and($agreement['mean_abs_delta'])->toBe(3.0)
        ->and($agreement['max_abs_delta'])->toBe(3);
});

test('zonder schoten valt er niets te vergelijken', function (): void {
    $agreement = metrics()->ringPositionAgreement([]);

    expect($agreement['shots'])->toBe(0)
        ->and($agreement['mean_abs_delta'])->toBeNull();
});

test('op een roos die bij ring 6 ophoudt loopt de ringschaal mee', function (): void {
    // Vijf ringen (6 tot en met 10) tussen het midden en de buitenrand.
    expect(metrics()->ringFromDistance(0.0, 6))->toBe(10)
        ->and(metrics()->ringFromDistance(0.5, 6))->toBe(8)
        ->and(metrics()->ringFromDistance(1.0, 6))->toBe(6)
        ->and(metrics()->ringFromDistance(1.2, 6))->toBe(0);
});

test('IMG_6462 geeft geen vals alarm meer zodra de buitenste ring bekend is', function (): void {
    // Dit zijn de echte waarden uit ronde 1: het model las ring 10 tot 6 af op een
    // kaart waarvan de buitenste gedrukte ring een 6 is. Afgerekend tegen ring 1
    // leverde dat zes ringen verschil op, terwijl het model gewoon gelijk had.
    $schoten = [
        shotAt(0.031, 0.0, 10), shotAt(0.349, 0.0, 9),
        shotAt(0.520, 0.0, 8), shotAt(0.736, 0.0, 7),
        shotAt(0.871, 0.0, 6),
    ];

    $tegenRing1 = metrics()->ringPositionAgreement($schoten, 1);
    $tegenRing6 = metrics()->ringPositionAgreement($schoten, 6);

    expect($tegenRing1['max_abs_delta'])->toBeGreaterThanOrEqual(3)
        ->and($tegenRing6['agree'])->toBe(5)
        ->and($tegenRing6['max_abs_delta'])->toBe(0);
});

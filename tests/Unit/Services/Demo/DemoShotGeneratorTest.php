<?php

declare(strict_types=1);

use App\Enums\Deviation;
use App\Services\Demo\DemoShotGenerator;
use App\Services\Sessions\ShotScoringService;

function demoShotGenerator(): DemoShotGenerator
{
    return new DemoShotGenerator(new ShotScoringService);
}

function demoShotMean(array $shots, string $key): float
{
    return array_sum(array_column($shots, $key)) / count($shots);
}

test('genereert exact het gevraagde aantal schoten', function (): void {
    expect(demoShotGenerator()->generate(30, Deviation::NONE, 1))->toHaveCount(30);
});

test('verdeelt schoten in series van tien met doorlopende indices', function (): void {
    $shots = demoShotGenerator()->generate(25, Deviation::NONE, 1);

    expect(array_column($shots, 'turn_index'))
        ->toBe([...array_fill(0, 10, 0), ...array_fill(0, 10, 1), ...array_fill(0, 5, 2)])
        ->and(array_slice(array_column($shots, 'shot_index'), 20))
        ->toBe([0, 1, 2, 3, 4]);
});

test('laat de turn_index doortellen vanaf een offset', function (): void {
    $shots = demoShotGenerator()->generate(10, Deviation::NONE, 1, turnOffset: 3);

    expect(array_unique(array_column($shots, 'turn_index')))->toBe([3]);
});

test('levert geldige coordinaten, ringen en scores', function (): void {
    foreach (demoShotGenerator()->generate(60, Deviation::LOW, 7) as $shot) {
        expect($shot['x_normalized'])->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(1.0)
            ->and($shot['y_normalized'])->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(1.0)
            ->and($shot['ring'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(ShotScoringService::MAX_SCORE)
            ->and($shot['score'])->toBe($shot['ring'])
            ->and($shot['distance_from_center'])->toBeGreaterThanOrEqual(0.0);
    }
});

test('is deterministisch: dezelfde seed geeft dezelfde groep', function (): void {
    expect(demoShotGenerator()->generate(20, Deviation::HIGH, 42))
        ->toBe(demoShotGenerator()->generate(20, Deviation::HIGH, 42));
});

test('een andere seed geeft een andere groep', function (): void {
    expect(demoShotGenerator()->generate(20, Deviation::HIGH, 42))
        ->not->toBe(demoShotGenerator()->generate(20, Deviation::HIGH, 43));
});

test('raakt de globale random-state van PHP niet aan', function (): void {
    mt_srand(12345);
    $before = mt_rand();

    mt_srand(12345);
    demoShotGenerator()->generate(30, Deviation::LEFT, 99);

    expect(mt_rand())->toBe($before);
});

test('zonder rondes komen er geen schoten', function (int $rounds): void {
    expect(demoShotGenerator()->generate($rounds, Deviation::NONE, 1))->toBe([]);
})->with([0, -5]);

test('turnsFor telt de series die bij een aantal rondes horen', function (int $rounds, int $expected): void {
    expect(demoShotGenerator()->turnsFor($rounds))->toBe($expected);
})->with([[0, 0], [-1, 0], [1, 1], [10, 1], [11, 2], [60, 6]]);

test('de afwijking van de wapenregel is terug te zien in de groep', function (
    Deviation $deviation,
    string $axis,
    string $direction,
): void {
    $shots = demoShotGenerator()->generate(80, $deviation, 5);
    $mean = demoShotMean($shots, $axis);

    // y = 0 is de bovenkant van de roos, dus "hoog" betekent een kleinere y.
    $direction === 'lager'
        ? expect($mean)->toBeLessThan(0.5)
        : expect($mean)->toBeGreaterThan(0.5);
})->with([
    'links trekt naar kleinere x' => [Deviation::LEFT, 'x_normalized', 'lager'],
    'rechts trekt naar grotere x' => [Deviation::RIGHT, 'x_normalized', 'hoger'],
    'hoog trekt naar kleinere y' => [Deviation::HIGH, 'y_normalized', 'lager'],
    'laag trekt naar grotere y' => [Deviation::LOW, 'y_normalized', 'hoger'],
]);

test('zonder afwijking blijft de groep rond het middelpunt', function (): void {
    $shots = demoShotGenerator()->generate(200, Deviation::NONE, 11);

    expect(abs(demoShotMean($shots, 'x_normalized') - 0.5))->toBeLessThan(0.02)
        ->and(abs(demoShotMean($shots, 'y_normalized') - 0.5))->toBeLessThan(0.02);
});

test('de groep ligt dicht genoeg bij het midden om een geloofwaardige demo te zijn', function (): void {
    $shots = demoShotGenerator()->generate(100, Deviation::NONE, 3);
    $average = array_sum(array_column($shots, 'score')) / count($shots);

    expect($average)->toBeGreaterThan(8.0);
});

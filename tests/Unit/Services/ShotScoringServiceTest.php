<?php

declare(strict_types=1);

use App\Services\Sessions\ShotScoringService;

it('derives a one-decimal score from the distance to the centre', function (float $distance, float $expected): void {
    expect((new ShotScoringService)->decimalScore($distance))->toBe($expected);
})->with([
    'bullseye' => [0.0, 10.0],
    'quarter' => [0.25, 5.0],
    'rim' => [0.5, 0.0],
    'near centre' => [0.1, 8.0],
]);

it('never returns a negative decimal score beyond the rim', function (): void {
    expect((new ShotScoringService)->decimalScore(0.9))->toBe(0.0);
});

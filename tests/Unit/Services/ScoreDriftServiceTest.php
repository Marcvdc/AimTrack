<?php

declare(strict_types=1);

use App\Models\Session;
use App\Models\SessionShot;
use App\Models\User;
use App\Services\ScoreDriftService;
use Carbon\CarbonInterface;

/**
 * @param  array<int, int>  $scores
 */
function driftSession(User $user, array $scores, CarbonInterface $date): void
{
    $session = Session::factory()->for($user)->create(['date' => $date]);
    foreach ($scores as $i => $score) {
        SessionShot::factory()->for($session)->create([
            'turn_index' => 0,
            'shot_index' => $i,
            'ring' => $score,
            'score' => $score,
        ]);
    }
}

it('averages score per shot position across recent sessions', function (): void {
    $user = User::factory()->create();
    driftSession($user, array_fill(0, 10, 10), now()->subDays(2));
    driftSession($user, [10, 10, 10, 10, 10, 8, 8, 8, 8, 8], now()->subDay());

    $avg = (new ScoreDriftService($user))->perShotAverage();

    expect($avg)->toHaveCount(10)
        ->and($avg[1])->toBe(10.0)
        ->and($avg[6])->toBe(9.0);
});

it('returns an empty drift when the shooter has no sessions with shots', function (): void {
    expect((new ScoreDriftService(User::factory()->create()))->perShotAverage())->toBe([]);
});

it('finds the weakest shot window', function (): void {
    $user = User::factory()->create();
    driftSession($user, [10, 10, 10, 10, 10, 8, 8, 8, 8, 8], now());

    $window = (new ScoreDriftService($user))->weakestWindow(5, 6);

    expect($window)->not->toBeNull()
        ->and($window['from'])->toBe(6)
        ->and($window['to'])->toBe(10)
        ->and($window['average'])->toBe(8.0);
});

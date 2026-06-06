<?php

declare(strict_types=1);

use App\Models\Session;
use App\Models\SessionShot;
use App\Models\SessionWeapon;
use App\Models\User;
use App\Models\Weapon;
use App\Services\WeaponInsightsService;

/**
 * Helper: seed N shots with unique (turn_index, shot_index) for a session.
 */
function shotsFor(\App\Models\Session $session, int $count, int $ring, int $score): void
{
    foreach (range(0, $count - 1) as $i) {
        SessionShot::factory()->for($session)->create([
            'turn_index' => intdiv($i, 10),
            'shot_index' => $i % 10,
            'ring' => $ring,
            'score' => $score,
        ]);
    }
}

it('returns zero KPIs when weapon has no sessions', function (): void {
    $weapon = Weapon::factory()->for(User::factory())->create();
    $service = new WeaponInsightsService($weapon);

    expect($service->sessionCount())->toBe(0);
    expect($service->totalShots())->toBe(0);
    expect($service->avgScore())->toBeNull();
    expect($service->bestScore())->toBeNull();
    expect($service->bestScoreDate())->toBeNull();
    expect($service->trendData())->toBe([]);
    expect($service->recentSessions())->toHaveCount(0);
});

it('counts distinct sessions for the weapon', function (): void {
    $user = User::factory()->create();
    $weapon = Weapon::factory()->for($user)->create();
    $otherWeapon = Weapon::factory()->for($user)->create();

    $sessionA = Session::factory()->for($user)->create();
    $sessionB = Session::factory()->for($user)->create();
    $sessionC = Session::factory()->for($user)->create();

    SessionWeapon::factory()->for($sessionA)->for($weapon)->create(['rounds_fired' => 60]);
    SessionWeapon::factory()->for($sessionB)->for($weapon)->create(['rounds_fired' => 40]);
    SessionWeapon::factory()->for($sessionC)->for($otherWeapon)->create(['rounds_fired' => 30]);

    $service = new WeaponInsightsService($weapon);

    expect($service->sessionCount())->toBe(2);
    expect($service->totalShots())->toBe(100);
});

it('computes avg and best score across sessions', function (): void {
    $user = User::factory()->create();
    $weapon = Weapon::factory()->for($user)->create();

    $sessions = Session::factory()->for($user)->count(3)->create();

    foreach ($sessions as $i => $session) {
        SessionWeapon::factory()->for($session)->for($weapon)->create(['rounds_fired' => 10]);
        shotsFor($session, 10, 9, 9 + $i);
    }

    $service = new WeaponInsightsService($weapon);

    expect($service->bestScore())->toBe(110);
    expect($service->avgScore())->toBe(100.0);
});

it('returns a date for bestScoreDate matching the top session', function (): void {
    $user = User::factory()->create();
    $weapon = Weapon::factory()->for($user)->create();

    $low = Session::factory()->for($user)->create(['date' => '2026-04-01']);
    $high = Session::factory()->for($user)->create(['date' => '2026-04-15']);

    SessionWeapon::factory()->for($low)->for($weapon)->create();
    SessionWeapon::factory()->for($high)->for($weapon)->create();
    shotsFor($low, 10, 8, 8);
    shotsFor($high, 10, 10, 10);

    $date = (new WeaponInsightsService($weapon))->bestScoreDate();

    expect($date?->toDateString())->toBe('2026-04-15');
});

it('breaks bestScoreDate ties in favour of the most recent session', function (): void {
    $user = User::factory()->create();
    $weapon = Weapon::factory()->for($user)->create();

    $earlier = Session::factory()->for($user)->create(['date' => '2026-03-01']);
    $later = Session::factory()->for($user)->create(['date' => '2026-04-20']);

    SessionWeapon::factory()->for($earlier)->for($weapon)->create();
    SessionWeapon::factory()->for($later)->for($weapon)->create();

    // Both sessions tie at the maximum score of 100.
    shotsFor($earlier, 10, 10, 10);
    shotsFor($later, 10, 10, 10);

    $date = (new WeaponInsightsService($weapon))->bestScoreDate();

    expect($date?->toDateString())->toBe('2026-04-20');
});

it('returns trend data only within the requested window', function (): void {
    $user = User::factory()->create();
    $weapon = Weapon::factory()->for($user)->create();

    $recent = Session::factory()->for($user)->create(['date' => now()->subDays(3)->toDateString()]);
    $stale = Session::factory()->for($user)->create(['date' => now()->subDays(90)->toDateString()]);

    SessionWeapon::factory()->for($recent)->for($weapon)->create();
    SessionWeapon::factory()->for($stale)->for($weapon)->create();
    shotsFor($recent, 5, 10, 10);
    shotsFor($stale, 5, 10, 10);

    $trend = (new WeaponInsightsService($weapon))->trendData(30);

    expect($trend)->toHaveCount(1);
    expect($trend)->toHaveKey($recent->date->toDateString());
    expect($trend[$recent->date->toDateString()])->toBe(50);
});

it('orders recentSessions desc by date with limit', function (): void {
    $user = User::factory()->create();
    $weapon = Weapon::factory()->for($user)->create();

    foreach (range(1, 7) as $i) {
        $session = Session::factory()->for($user)->create(['date' => now()->subDays($i)->toDateString()]);
        SessionWeapon::factory()->for($session)->for($weapon)->create();
    }

    $recent = (new WeaponInsightsService($weapon))->recentSessions(3);

    expect($recent)->toHaveCount(3);
    expect($recent->first()->date->toDateString())->toBe(now()->subDays(1)->toDateString());
});

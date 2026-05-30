<?php

declare(strict_types=1);

use App\Models\AiReflection;
use App\Models\Session;
use App\Models\SessionShot;
use App\Models\User;
use App\Services\RangeConsoleSummaryService;

/**
 * @param  array<string, mixed>  $overrides
 */
function summaryShotsFor(Session $session, int $count, array $overrides = []): void
{
    foreach (range(0, $count - 1) as $i) {
        SessionShot::factory()->for($session)->create(array_merge([
            'turn_index' => intdiv($i, 10),
            'shot_index' => $i % 10,
        ], $overrides));
    }
}

it('returns zero KPIs for a user with no sessions', function (): void {
    $service = new RangeConsoleSummaryService(User::factory()->create());

    expect($service->sessionsThisMonth())->toBe(0);
    expect($service->sessionsThisMonthDelta())->toBe(0);
    expect($service->shotsLast30d())->toBe(0);
    expect($service->bestSeriesScore())->toBeNull();
    expect($service->aiReflectionCount())->toBe(0);
    expect($service->pendingAiReflections())->toBe(0);
    expect($service->lastSession())->toBeNull();
    expect($service->latestReflection())->toBeNull();
    expect($service->trend30d())->toBe([]);
});

it('counts sessions in current and previous month separately', function (): void {
    $user = User::factory()->create();

    Session::factory()->for($user)->count(3)->create(['date' => now()->startOfMonth()->addDays(2)]);
    Session::factory()->for($user)->count(2)->create(['date' => now()->subMonthNoOverflow()->startOfMonth()->addDays(5)]);

    $service = new RangeConsoleSummaryService($user);

    expect($service->sessionsThisMonth())->toBe(3);
    // sessionsLastMonth() is een implementatiedetail van de delta (privé);
    // delta = dezeMaand - vorigeMaand = 3 - 2 = 1 borgt beide.
    expect($service->sessionsThisMonthDelta())->toBe(1);
});

it('scopes shotsLast30d to the user and last 30 days', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $recent = Session::factory()->for($user)->create(['date' => now()->subDays(3)]);
    $stale = Session::factory()->for($user)->create(['date' => now()->subDays(50)]);
    $otherSession = Session::factory()->for($other)->create(['date' => now()->subDays(1)]);

    summaryShotsFor($recent, 6);
    summaryShotsFor($stale, 5);
    summaryShotsFor($otherSession, 8);

    expect((new RangeConsoleSummaryService($user))->shotsLast30d())->toBe(6);
});

it('picks the best 10-shot serie across all user sessions', function (): void {
    $user = User::factory()->create();

    $sessionA = Session::factory()->for($user)->create();
    summaryShotsFor($sessionA, 10, ['ring' => 9, 'score' => 9]);

    $sessionB = Session::factory()->for($user)->create();
    summaryShotsFor($sessionB, 10, ['ring' => 10, 'score' => 10]);

    $sessionC = Session::factory()->for($user)->create();
    summaryShotsFor($sessionC, 7, ['ring' => 10, 'score' => 10]);

    expect((new RangeConsoleSummaryService($user))->bestSeriesScore())->toBe(100);
});

it('counts AI reflections only for the user own sessions', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $mine = Session::factory()->for($user)->count(2)->create();
    $theirs = Session::factory()->for($other)->create();

    foreach ($mine as $session) {
        AiReflection::factory()->for($session)->create();
    }
    AiReflection::factory()->for($theirs)->create();

    expect((new RangeConsoleSummaryService($user))->aiReflectionCount())->toBe(2);
});

it('counts pending AI reflections as recent sessions without reflection', function (): void {
    $user = User::factory()->create();

    $recentWithoutReflection = Session::factory()->for($user)->create(['date' => now()->subDays(5)]);
    $recentWithReflection = Session::factory()->for($user)->create(['date' => now()->subDays(3)]);
    AiReflection::factory()->for($recentWithReflection)->create();

    $staleWithoutReflection = Session::factory()->for($user)->create(['date' => now()->subDays(40)]);

    expect((new RangeConsoleSummaryService($user))->pendingAiReflections())->toBe(1);
});

it('returns lastSession as the most recent by date', function (): void {
    $user = User::factory()->create();

    Session::factory()->for($user)->create(['date' => now()->subDays(10)]);
    $newest = Session::factory()->for($user)->create(['date' => now()->subDays(2)]);
    Session::factory()->for($user)->create(['date' => now()->subDays(5)]);

    expect((new RangeConsoleSummaryService($user))->lastSession()?->id)->toBe($newest->id);
});

it('returns trend30d with sessions only from the last 30 days', function (): void {
    $user = User::factory()->create();

    $recent = Session::factory()->for($user)->create(['date' => now()->subDays(7)]);
    $stale = Session::factory()->for($user)->create(['date' => now()->subDays(60)]);

    summaryShotsFor($recent, 5, ['ring' => 10, 'score' => 10]);
    summaryShotsFor($stale, 5, ['ring' => 10, 'score' => 10]);

    $trend = (new RangeConsoleSummaryService($user))->trend30d();

    expect($trend)->toHaveCount(1);
    expect($trend)->toHaveKey($recent->date->toDateString());
    expect($trend[$recent->date->toDateString()])->toBe(50);
});

<?php

declare(strict_types=1);

use App\Models\Session;
use App\Models\SessionShot;
use App\Models\User;
use App\Services\SessionStatsService;

/**
 * Helper: seed N shots with unique (turn_index, shot_index) for a session.
 * Overrides can include ring/score/x_normalized/y_normalized/etc.
 *
 * @param  array<string, mixed>  $overrides
 */
function statsShotsFor(Session $session, int $count, array $overrides = []): void
{
    foreach (range(0, $count - 1) as $i) {
        SessionShot::factory()->for($session)->create(array_merge([
            'turn_index' => intdiv($i, 10),
            'shot_index' => $i % 10,
        ], $overrides));
    }
}

it('returns 0 for tienen/negens/totalScore when session has no shots', function (): void {
    $session = Session::factory()->for(User::factory())->create();
    $service = new SessionStatsService($session);

    expect($service->totalShots())->toBe(0);
    expect($service->tienen())->toBe(0);
    expect($service->negens())->toBe(0);
    expect($service->totalScore())->toBe(0);
    expect($service->bestShot())->toBeNull();
    expect($service->scores())->toBe([]);
});

it('counts tienen and negens by ring not score', function (): void {
    $session = Session::factory()->for(User::factory())->create();

    foreach ([[10, 10], [10, 10], [9, 9], [8, 8]] as $i => [$ring, $score]) {
        SessionShot::factory()->for($session)->create([
            'turn_index' => 0,
            'shot_index' => $i,
            'ring' => $ring,
            'score' => $score,
        ]);
    }

    $service = new SessionStatsService($session);

    expect($service->tienen())->toBe(2);
    expect($service->negens())->toBe(1);
    expect($service->totalShots())->toBe(4);
    expect($service->totalScore())->toBe(37);
    expect($service->bestShot())->toBe(10);
});

it('chunks series scores into groups of perSerie', function (): void {
    $session = Session::factory()->for(User::factory())->create();

    foreach (range(1, 20) as $i) {
        SessionShot::factory()->for($session)->create([
            'turn_index' => intdiv($i - 1, 10),
            'shot_index' => ($i - 1) % 10,
            'ring' => 10,
            'score' => 10,
        ]);
    }

    $service = new SessionStatsService($session);

    expect($service->seriesScores(10))->toBe([100, 100]);
    expect($service->shotsPerSerie())->toBe(10);
});

it('drops a partial last series from seriesScores', function (): void {
    $session = Session::factory()->for(User::factory())->create();

    foreach (range(1, 13) as $i) {
        SessionShot::factory()->for($session)->create([
            'turn_index' => intdiv($i - 1, 10),
            'shot_index' => ($i - 1) % 10,
            'ring' => 9,
            'score' => 9,
        ]);
    }

    $service = new SessionStatsService($session);

    expect($service->seriesScores(10))->toBe([90]);
});

it('detects the dip range as the lowest-scoring serie', function (): void {
    $session = Session::factory()->for(User::factory())->create();

    foreach (range(0, 29) as $i) {
        $turn = intdiv($i, 10);
        SessionShot::factory()->for($session)->create([
            'turn_index' => $turn,
            'shot_index' => $i % 10,
            'ring' => $turn === 1 ? 7 : 10,
            'score' => $turn === 1 ? 7 : 10,
        ]);
    }

    $service = new SessionStatsService($session);

    expect($service->dipRange())->toBe([10, 19]);
});

it('returns null dipRange when fewer than 2 complete series', function (): void {
    $session = Session::factory()->for(User::factory())->create();
    statsShotsFor($session, 8, ['ring' => 10, 'score' => 10]);

    expect((new SessionStatsService($session))->dipRange())->toBeNull();
});

it('returns null dipRange when all series score equally (no real dip)', function (): void {
    $session = Session::factory()->for(User::factory())->create();
    statsShotsFor($session, 20, ['ring' => 10, 'score' => 10]);

    expect((new SessionStatsService($session))->dipRange())->toBeNull();
});

it('returns null groupMm when fewer than 2 shots', function (): void {
    $session = Session::factory()->for(User::factory())->create();
    SessionShot::factory()->for($session)->create([
        'turn_index' => 0,
        'shot_index' => 0,
        'x_normalized' => 0.1,
        'y_normalized' => 0.0,
    ]);

    expect((new SessionStatsService($session))->groupMm())->toBeNull();
});

it('returns positive groupMm in millimetres for multiple shots', function (): void {
    $session = Session::factory()->for(User::factory())->create();
    foreach ([[0.0, 0.0], [0.05, -0.05], [-0.05, 0.05]] as $i => [$x, $y]) {
        SessionShot::factory()->for($session)->create([
            'turn_index' => 0,
            'shot_index' => $i,
            'x_normalized' => $x,
            'y_normalized' => $y,
        ]);
    }

    $group = (new SessionStatsService($session))->groupMm();

    expect($group)->toBeFloat()->and($group)->toBeGreaterThan(0.0);
});

it('returns null avgCadansSec when shots have no time spread', function (): void {
    $session = Session::factory()->for(User::factory())->create();
    statsShotsFor($session, 5);

    // Force all shots to share created_at so deltas are zero.
    $session->shots()->update(['created_at' => now()]);

    expect((new SessionStatsService($session))->avgCadansSec())->toBeNull();
});

it('averages only positive deltas, skipping same-second shots', function (): void {
    $session = Session::factory()->for(User::factory())->create();
    $base = now()->startOfMinute();

    // Deltas: shot1->2 = 0s (same second, skipped), 2->3 = 4s, 3->4 = 2s.
    // Expected average over positive deltas = (4 + 2) / 2 = 3.0.
    $stamps = [$base, $base, $base->copy()->addSeconds(4), $base->copy()->addSeconds(6)];
    foreach ($stamps as $i => $stamp) {
        SessionShot::factory()->for($session)->create([
            'turn_index' => 0,
            'shot_index' => $i,
            'created_at' => $stamp,
        ]);
    }

    expect((new SessionStatsService($session))->avgCadansSec())->toBe(3.0);
});

it('caches the loaded shots collection across method calls', function (): void {
    $session = Session::factory()->for(User::factory())->create();
    statsShotsFor($session, 3, ['ring' => 10, 'score' => 10]);

    $service = new SessionStatsService($session);

    $service->totalShots();

    \DB::enableQueryLog();
    $service->tienen();
    $service->negens();
    $service->totalScore();
    $queries = collect(\DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'session_shots'));

    expect($queries->count())->toBe(0);
});

it('shotsPerSerie defaults to 10 for empty sessions and longer ones', function (): void {
    $empty = Session::factory()->for(User::factory())->create();
    expect((new SessionStatsService($empty))->shotsPerSerie())->toBe(10);

    $small = Session::factory()->for(User::factory())->create();
    statsShotsFor($small, 5);
    expect((new SessionStatsService($small))->shotsPerSerie())->toBe(5);
});

it('computes mean X/Y offset in mm relative to the centre', function (): void {
    $session = Session::factory()->for(User::factory())->create();
    statsShotsFor($session, 4, ['x_normalized' => 0.5, 'y_normalized' => 0.5, 'ring' => 10, 'score' => 10]);

    $service = new SessionStatsService($session);

    expect($service->meanXmm())->toBe(0.0)
        ->and($service->meanYmm())->toBe(0.0);
});

it('returns null mean offset and SD when there are no shots', function (): void {
    $service = new SessionStatsService(Session::factory()->for(User::factory())->create());

    expect($service->meanXmm())->toBeNull()
        ->and($service->meanYmm())->toBeNull()
        ->and($service->sdMm())->toBeNull();
});

it('computes a positive standard deviation in mm for spread shots', function (): void {
    $session = Session::factory()->for(User::factory())->create();
    SessionShot::factory()->for($session)->create(['turn_index' => 0, 'shot_index' => 0, 'x_normalized' => 0.4, 'y_normalized' => 0.5, 'ring' => 9, 'score' => 9]);
    SessionShot::factory()->for($session)->create(['turn_index' => 0, 'shot_index' => 1, 'x_normalized' => 0.6, 'y_normalized' => 0.5, 'ring' => 9, 'score' => 9]);

    expect((new SessionStatsService($session))->sdMm())->toBeGreaterThan(0.0);
});

it('computes the score delta versus the user session average', function (): void {
    $user = User::factory()->create();

    $a = Session::factory()->for($user)->create();
    statsShotsFor($a, 10, ['ring' => 10, 'score' => 10]); // totaal 100

    $b = Session::factory()->for($user)->create();
    statsShotsFor($b, 10, ['ring' => 8, 'score' => 8]);   // totaal 80

    // gemiddelde van [100, 80] = 90 → 100 - 90 = +10
    expect((new SessionStatsService($a))->scoreVsAverage())->toBe(10);
});

it('returns null score delta with fewer than two sessions', function (): void {
    $user = User::factory()->create();
    $a = Session::factory()->for($user)->create();
    statsShotsFor($a, 5, ['ring' => 10, 'score' => 10]);

    expect((new SessionStatsService($a))->scoreVsAverage())->toBeNull();
});

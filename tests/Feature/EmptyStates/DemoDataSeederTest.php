<?php

declare(strict_types=1);

use App\Models\AiReflection;
use App\Models\Session;
use App\Models\SessionShot;
use App\Models\User;
use App\Models\Weapon;
use App\Services\DemoDataSeeder;
use App\Services\SeedResult;
use App\Services\SessionStatsService;
use App\Support\DateFormat;
use App\Support\UserOnboardingState;

test('seedFor creates three weapons, five sessions and three AI reflections for the user', function (): void {
    $user = User::factory()->create();

    $result = app(DemoDataSeeder::class)->seedFor($user);

    expect($result)->toBe(SeedResult::Seeded)
        ->and($user->weapons()->count())->toBe(DemoDataSeeder::WEAPON_COUNT)
        ->and($user->sessions()->count())->toBe(DemoDataSeeder::SESSION_COUNT)
        ->and(AiReflection::query()->whereIn('session_id', $user->sessions()->pluck('id'))->count())
        ->toBe(DemoDataSeeder::REFLECTION_COUNT);
});

test('seedFor sets the demo_data_seeded_at marker on the user', function (): void {
    $user = User::factory()->create(['demo_data_seeded_at' => null]);

    expect($user->demo_data_seeded_at)->toBeNull();

    app(DemoDataSeeder::class)->seedFor($user);

    expect($user->fresh()->demo_data_seeded_at)->not->toBeNull();
});

test('seedFor is idempotent — second call returns AlreadyLoaded and creates no duplicates', function (): void {
    $user = User::factory()->create();
    $seeder = app(DemoDataSeeder::class);

    $first = $seeder->seedFor($user);
    $weaponsAfterFirst = $user->weapons()->count();
    $sessionsAfterFirst = $user->sessions()->count();

    $second = $seeder->seedFor($user);

    expect($first)->toBe(SeedResult::Seeded)
        ->and($second)->toBe(SeedResult::AlreadyLoaded)
        ->and($user->weapons()->count())->toBe($weaponsAfterFirst)
        ->and($user->sessions()->count())->toBe($sessionsAfterFirst);
});

test('seedFor results in an unlocked AI-coach (>= 3 sessions)', function (): void {
    $user = User::factory()->create();

    app(DemoDataSeeder::class)->seedFor($user);

    expect((new UserOnboardingState($user->fresh()))->aiCoachUnlocked())->toBeTrue();
});

test('seedFor scopes records strictly to the target user', function (): void {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    app(DemoDataSeeder::class)->seedFor($alice);

    expect($alice->weapons()->count())->toBe(3)
        ->and($alice->sessions()->count())->toBe(5)
        ->and($bob->weapons()->count())->toBe(0)
        ->and($bob->sessions()->count())->toBe(0);
});

test('purgeFor wipes demo data and resets the marker', function (): void {
    $user = User::factory()->create();
    $seeder = app(DemoDataSeeder::class);

    $seeder->seedFor($user);

    expect($user->fresh()->demo_data_seeded_at)->not->toBeNull();

    $seeder->purgeFor($user);

    expect($user->fresh()->demo_data_seeded_at)->toBeNull()
        ->and(Weapon::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and(Session::query()->where('user_id', $user->id)->count())->toBe(0);
});

test('CopilotDemoSeeder forcefully reseeds the admin@aimtrack.test user', function (): void {
    $seeder = new \Database\Seeders\CopilotDemoSeeder;
    $seeder->run();

    $first = User::query()->where('email', 'admin@aimtrack.test')->firstOrFail();

    expect($first->weapons()->count())->toBe(3)
        ->and($first->sessions()->count())->toBe(5);

    // Running again should give the same counts (purge + reseed).
    $seeder->run();

    $second = User::query()->where('email', 'admin@aimtrack.test')->firstOrFail();

    expect($second->id)->toBe($first->id)
        ->and($second->weapons()->count())->toBe(3)
        ->and($second->sessions()->count())->toBe(5);
});

test('seedFor gives every demo session real shots', function (): void {
    $user = User::factory()->create();

    app(DemoDataSeeder::class)->seedFor($user);

    foreach ($user->sessions()->get() as $session) {
        $stats = new SessionStatsService($session);

        expect($stats->totalShots())->toBeGreaterThan(0)
            ->and($stats->totalScore())->toBeGreaterThan(0);
    }
});

test('the shot count matches the rounds_fired the weapon lines claim', function (): void {
    $user = User::factory()->create();

    app(DemoDataSeeder::class)->seedFor($user);

    foreach ($user->sessions()->with('sessionWeapons')->get() as $session) {
        expect($session->shots()->count())->toBe((int) $session->sessionWeapons->sum('rounds_fired'));
    }
});

test('the promise on the demo button matches what the seeder writes', function (): void {
    $user = User::factory()->create();

    app(DemoDataSeeder::class)->seedFor($user);

    $shots = SessionShot::query()->whereIn('session_id', $user->sessions()->pluck('id'))->count();

    expect($shots)->toBe(DemoDataSeeder::SHOT_COUNT);
});

test('the bullseye is filled: tens, nines and a best shot are present', function (): void {
    $user = User::factory()->create();

    app(DemoDataSeeder::class)->seedFor($user);

    $session = $user->sessions()->orderByDesc('date')->first();
    $stats = new SessionStatsService($session);

    expect($stats->tienen())->toBeGreaterThan(0)
        ->and($stats->negens())->toBeGreaterThan(0)
        ->and($stats->bestShot())->toBe(10)
        ->and($stats->groupMm())->not->toBeNull()
        ->and($stats->seriesScores())->not->toBe([]);
});

test('the shot pattern follows the storyline of the reflection text', function (): void {
    $user = User::factory()->create();

    app(DemoDataSeeder::class)->seedFor($user);

    $sessions = $user->sessions()->with('sessionWeapons.weapon')->orderByDesc('date')->get();

    // Sessie 2 (6 dagen terug) is de Glock-snelvuursessie met de left-pull.
    $glockSession = $sessions->firstWhere(fn (Session $s): bool => $s->sessionWeapons
        ->contains(fn ($line): bool => $line->weapon?->name === 'Glock 17' && $line->deviation === App\Enums\Deviation::LEFT));

    expect($glockSession)->not->toBeNull()
        ->and((new SessionStatsService($glockSession))->meanXmm())->toBeLessThan(0.0);
});

test('shots are staggered in time so cadence and duration are not empty', function (): void {
    $user = User::factory()->create();

    app(DemoDataSeeder::class)->seedFor($user);

    $session = $user->sessions()->orderByDesc('date')->first();

    expect((new SessionStatsService($session))->avgCadansSec())->toBeGreaterThan(0.0);
});

test('a demo session starts at 10:00 local time, stored as UTC', function (): void {
    $user = User::factory()->create();

    app(DemoDataSeeder::class)->seedFor($user);

    $session = $user->sessions()->orderByDesc('date')->first();
    $firstShot = $session->shots()->orderBy('turn_index')->orderBy('shot_index')->first();

    expect($firstShot->created_at->timezone->getName())->toBe('UTC')
        ->and(DateFormat::time($firstShot->created_at))->toBe('10:00');
});

test('the demo data is identical for every user', function (): void {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $seeder = app(DemoDataSeeder::class);

    $seeder->seedFor($alice);
    $seeder->seedFor($bob);

    $scoresFor = fn (User $user): array => SessionShot::query()
        ->whereIn('session_id', $user->sessions()->orderBy('date')->pluck('id'))
        ->orderBy('session_id')
        ->orderBy('turn_index')
        ->orderBy('shot_index')
        ->pluck('score')
        ->all();

    expect($scoresFor($alice))->toBe($scoresFor($bob));
});

test('purgeFor removes the shots along with the sessions', function (): void {
    $user = User::factory()->create();
    $seeder = app(DemoDataSeeder::class);

    $seeder->seedFor($user);
    $sessionIds = $user->sessions()->pluck('id');

    expect(SessionShot::query()->whereIn('session_id', $sessionIds)->count())->toBe(DemoDataSeeder::SHOT_COUNT);

    $seeder->purgeFor($user);

    expect(SessionShot::query()->whereIn('session_id', $sessionIds)->count())->toBe(0);
});

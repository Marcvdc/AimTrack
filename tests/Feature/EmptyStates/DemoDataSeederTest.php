<?php

declare(strict_types=1);

use App\Models\AiReflection;
use App\Models\Session;
use App\Models\User;
use App\Models\Weapon;
use App\Services\DemoDataSeeder;
use App\Services\SeedResult;
use App\Support\UserOnboardingState;

test('seedFor creates three weapons, five sessions and three AI reflections for the user', function (): void {
    $user = User::factory()->create();

    $result = app(DemoDataSeeder::class)->seedFor($user);

    expect($result)->toBe(SeedResult::Seeded)
        ->and($user->weapons()->count())->toBe(3)
        ->and($user->sessions()->count())->toBe(5)
        ->and(AiReflection::query()->whereIn('session_id', $user->sessions()->pluck('id'))->count())->toBe(3);
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

<?php

use App\Enums\VerenigingRol;
use App\Models\User;
use App\Models\Vereniging;

it('koppelt een user met een rol aan een vereniging', function (): void {
    $vereniging = Vereniging::factory()->create();
    $user = User::factory()->create();

    $vereniging->members()->attach($user, [
        'role' => VerenigingRol::Coach->value,
        'joined_at' => now(),
    ]);

    expect($user->rolInVereniging($vereniging))->toBe(VerenigingRol::Coach);
});

it('geeft null als de user geen lid is', function (): void {
    $vereniging = Vereniging::factory()->create();
    $user = User::factory()->create();

    expect($user->rolInVereniging($vereniging))->toBeNull();
});

it('staat een user maar eenmaal in dezelfde vereniging toe', function (): void {
    $vereniging = Vereniging::factory()->create();
    $user = User::factory()->create();

    $vereniging->members()->attach($user, ['role' => VerenigingRol::Member->value]);

    expect(fn () => $vereniging->members()->attach($user, ['role' => VerenigingRol::Admin->value]))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('coaches() levert alleen coaches en beheerders', function (): void {
    $vereniging = Vereniging::factory()->create();
    $lid = User::factory()->create();
    $coach = User::factory()->create();
    $admin = User::factory()->create();

    $vereniging->members()->attach($lid, ['role' => VerenigingRol::Member->value]);
    $vereniging->members()->attach($coach, ['role' => VerenigingRol::Coach->value]);
    $vereniging->members()->attach($admin, ['role' => VerenigingRol::Admin->value]);

    $coachIds = $vereniging->coaches()->pluck('users.id');

    expect($coachIds)->toContain($coach->id, $admin->id)
        ->not->toContain($lid->id);
});

it('isCoachVan geldt voor leden binnen dezelfde actieve vereniging', function (): void {
    $vereniging = Vereniging::factory()->create();
    $coach = User::factory()->create(['active_vereniging_id' => $vereniging->id]);
    $lid = User::factory()->create(['active_vereniging_id' => $vereniging->id]);

    $vereniging->members()->attach($coach, ['role' => VerenigingRol::Coach->value]);
    $vereniging->members()->attach($lid, ['role' => VerenigingRol::Member->value]);

    expect($coach->isCoachVan($lid))->toBeTrue();
});

it('isCoachVan geldt niet voor een member zonder coachrol', function (): void {
    $vereniging = Vereniging::factory()->create();
    $lidA = User::factory()->create(['active_vereniging_id' => $vereniging->id]);
    $lidB = User::factory()->create(['active_vereniging_id' => $vereniging->id]);

    $vereniging->members()->attach($lidA, ['role' => VerenigingRol::Member->value]);
    $vereniging->members()->attach($lidB, ['role' => VerenigingRol::Member->value]);

    expect($lidA->isCoachVan($lidB))->toBeFalse();
});

it('isCoachVan geldt niet over verenigingen heen', function (): void {
    $clubA = Vereniging::factory()->create();
    $clubB = Vereniging::factory()->create();
    $coach = User::factory()->create(['active_vereniging_id' => $clubA->id]);
    $vreemde = User::factory()->create(['active_vereniging_id' => $clubB->id]);

    $clubA->members()->attach($coach, ['role' => VerenigingRol::Coach->value]);
    $clubB->members()->attach($vreemde, ['role' => VerenigingRol::Member->value]);

    expect($coach->isCoachVan($vreemde))->toBeFalse();
});

it('slaat de verenigings-key encrypted op en verbergt deze', function (): void {
    $vereniging = Vereniging::factory()->withKey('sk-ant-geheim')->create();

    $raw = DB::table('verenigingen')->where('id', $vereniging->id)->value('anthropic_api_key');

    expect($raw)->not->toBe('sk-ant-geheim')
        ->and($vereniging->anthropic_api_key)->toBe('sk-ant-geheim')
        ->and($vereniging->toArray())->not->toHaveKey('anthropic_api_key');
});

<?php

use App\Models\User;
use App\Models\Vereniging;
use App\Services\Ai\AiKeyResolver;

it('geeft de key van een user terug', function (): void {
    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-abc']);

    expect(app(AiKeyResolver::class)->forUser($user))->toBe('sk-ant-abc');
});

it('geeft null als de user geen key heeft', function (): void {
    $user = User::factory()->create(['anthropic_api_key' => null]);

    expect(app(AiKeyResolver::class)->forUser($user))->toBeNull();
});

it('geeft null voor een null-user', function (): void {
    expect(app(AiKeyResolver::class)->forUser(null))->toBeNull();
});

it('resolvet de ingelogde user via forCurrentUser', function (): void {
    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-xyz']);
    $this->actingAs($user);

    expect(app(AiKeyResolver::class)->forCurrentUser())->toBe('sk-ant-xyz');
});

it('geeft null bij forCurrentUser zonder ingelogde user', function (): void {
    expect(app(AiKeyResolver::class)->forCurrentUser())->toBeNull();
});

it('valt terug op de verenigings-key als de user geen eigen key heeft', function (): void {
    $vereniging = Vereniging::factory()->withKey('sk-ant-vereniging')->create();
    $user = User::factory()->create([
        'anthropic_api_key' => null,
        'active_vereniging_id' => $vereniging->id,
    ]);

    expect(app(AiKeyResolver::class)->forUser($user))->toBe('sk-ant-vereniging');
});

it('laat de eigen user-key voorgaan op de verenigings-key', function (): void {
    $vereniging = Vereniging::factory()->withKey('sk-ant-vereniging')->create();
    $user = User::factory()->create([
        'anthropic_api_key' => 'sk-ant-eigen',
        'active_vereniging_id' => $vereniging->id,
    ]);

    expect(app(AiKeyResolver::class)->forUser($user))->toBe('sk-ant-eigen');
});

it('geeft null als noch user noch vereniging een key heeft', function (): void {
    $vereniging = Vereniging::factory()->create(['anthropic_api_key' => null]);
    $user = User::factory()->create([
        'anthropic_api_key' => null,
        'active_vereniging_id' => $vereniging->id,
    ]);

    expect(app(AiKeyResolver::class)->forUser($user))->toBeNull();
});

it('geeft de verenigings-key terug via forVereniging', function (): void {
    $vereniging = Vereniging::factory()->withKey('sk-ant-club')->create();

    expect(app(AiKeyResolver::class)->forVereniging($vereniging))->toBe('sk-ant-club');
    expect(app(AiKeyResolver::class)->forVereniging(null))->toBeNull();
});

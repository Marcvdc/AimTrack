<?php

use App\Models\User;
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

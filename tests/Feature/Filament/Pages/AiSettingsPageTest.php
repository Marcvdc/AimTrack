<?php

use App\Filament\Pages\AiSettingsPage;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('slaat een ingevoerde key op het user-record op', function (): void {
    $user = User::factory()->create(['anthropic_api_key' => null]);

    Livewire::actingAs($user)
        ->test(AiSettingsPage::class)
        ->set('data.anthropic_api_key', 'sk-ant-new-key')
        ->call('save');

    expect($user->fresh()->anthropic_api_key)->toBe('sk-ant-new-key');
});

it('test-actie meldt succes en zet ai_key_verified_at bij een geldige key (200)', function (): void {
    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-key', 'ai_key_verified_at' => null]);
    Http::fake(['api.anthropic.com/*' => Http::response(['data' => []], 200)]);

    Livewire::actingAs($user)
        ->test(AiSettingsPage::class)
        ->callAction('test')
        ->assertNotified();

    expect($user->fresh()->ai_key_verified_at)->not->toBeNull();
});

it('test-actie meldt fout bij een ongeldige key (401)', function (): void {
    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-bad', 'ai_key_verified_at' => null]);
    Http::fake(['api.anthropic.com/*' => Http::response(['type' => 'error'], 401)]);

    Livewire::actingAs($user)
        ->test(AiSettingsPage::class)
        ->callAction('test')
        ->assertNotified();

    expect($user->fresh()->ai_key_verified_at)->toBeNull();
});

it('wist de key via de clear-actie', function (): void {
    $user = User::factory()->withAnthropicKey()->create();

    Livewire::actingAs($user)
        ->test(AiSettingsPage::class)
        ->callAction('clear');

    expect($user->fresh()->anthropic_api_key)->toBeNull()
        ->and($user->fresh()->ai_key_verified_at)->toBeNull();
});

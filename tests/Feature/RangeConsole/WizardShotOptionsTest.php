<?php

declare(strict_types=1);

use App\Filament\Resources\SessionResource\Pages\CreateSession;
use App\Models\User;
use Livewire\Livewire;

it('initialises the Schoten-step options from the user preferences', function (): void {
    $user = User::factory()->create();
    $user->setPreference('board_show_rings', true);

    Livewire::actingAs($user)
        ->test(CreateSession::class)
        ->assertSet('boardPreferences.board_show_rings', true)
        ->assertSet('boardPreferences.decimal_notation', true)   // default
        ->assertSet('boardPreferences.auto_ai_reflection', false); // default
});

it('persists a toggled option to the user', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateSession::class)
        ->call('togglePreference', 'board_show_rings')
        ->assertSet('boardPreferences.board_show_rings', true);

    expect($user->fresh()->preference('board_show_rings'))->toBeTrue();
});

it('toggles a preference back off', function (): void {
    $user = User::factory()->create();
    $user->setPreference('decimal_notation', true);

    Livewire::actingAs($user)
        ->test(CreateSession::class)
        ->call('togglePreference', 'decimal_notation')
        ->assertSet('boardPreferences.decimal_notation', false);

    expect($user->fresh()->preference('decimal_notation'))->toBeFalse();
});

it('ignores unknown preference keys when toggling', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateSession::class)
        ->call('togglePreference', 'totally_unknown');

    expect($user->fresh()->preference('totally_unknown'))->toBeNull();
});

it('renders the Schoten-step options as interactive toggles', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateSession::class)
        ->assertSee('AI-reflectie')
        ->assertSee('Decimaal-notatie')
        ->assertSee('Toon ringen-view')
        ->assertSeeHtml("togglePreference('board_show_rings')")
        ->assertSeeHtml("togglePreference('decimal_notation')")
        ->assertSeeHtml("togglePreference('auto_ai_reflection')");
});

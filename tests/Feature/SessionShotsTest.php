<?php

use App\Models\Session;
use App\Models\SessionShot;
use App\Models\User;
use Livewire\Livewire;

test('renders session shot board with initial data', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session])
        ->assertSet('session.id', $session->id)
        ->assertSet('canEdit', true)
        ->assertSet('currentTurnIndex', 0)
        ->assertSet('lastActiveTurnIndex', 0)
        ->assertSee('Beurt 1');
});

test('shows all turns option in dropdown', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session])
        ->assertSee('Alle beurten')
        ->assertSee('Beurt 1');
});

test('can switch to all turns view', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);

    // Create shots in different turns
    SessionShot::factory()->create([
        'session_id' => $session->id,
        'turn_index' => 0,
        'x_normalized' => 0.5,
        'y_normalized' => 0.5,
    ]);
    SessionShot::factory()->create([
        'session_id' => $session->id,
        'turn_index' => 1,
        'x_normalized' => 0.3,
        'y_normalized' => 0.7,
    ]);

    $component = Livewire::actingAs($user)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session]);

    // Switch to all turns
    $component->set('currentTurnIndex', -1)
        ->assertSet('currentTurnIndex', -1);

    // Should show markers for both turns
    $markers = $component->get('markers');
    expect($markers)->toHaveCount(2);
    expect($markers[0])->toHaveKey('turn_label');
    expect($markers[1])->toHaveKey('turn_label');
    expect($markers[0])->toHaveKey('color');
    expect($markers[1])->toHaveKey('color');
});

test('prevents recording shots when all turns is selected', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session]);

    // Set to all turns
    $component->set('currentTurnIndex', -1);

    // Try to record a shot
    $component->call('recordShot', 0.5, 0.5);

    // Should show warning notification
    $component->assertNotified('Selecteer eerst een specifieke beurt om een schot te registreren.');
});

test('can delete a shot', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);
    $shot = SessionShot::factory()->create([
        'session_id' => $session->id,
        'turn_index' => 0,
        'x_normalized' => 0.5,
        'y_normalized' => 0.5,
    ]);

    $component = Livewire::actingAs($user)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session]);

    // Delete the shot
    $component->call('deleteShot', $shot->id);

    // Shot should be deleted
    $this->assertDatabaseMissing('session_shots', ['id' => $shot->id]);

    // Should show success notification
    $component->assertNotified('Schot verwijderd.');
});

test('table filter syncs with turn selection', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);

    // Create shots in different turns
    SessionShot::factory()->create([
        'session_id' => $session->id,
        'turn_index' => 0,
    ]);
    SessionShot::factory()->create([
        'session_id' => $session->id,
        'turn_index' => 1,
    ]);

    $component = Livewire::actingAs($user)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session]);

    // Switch to all turns
    $component->set('currentTurnIndex', -1);

    // Table filter should be updated
    $tableFilters = $component->get('tableFilters');
    expect($tableFilters['turn_scope']['value'])->toBe('all');
});

test('markers include turn labels and colors', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);

    $shot = SessionShot::factory()->create([
        'session_id' => $session->id,
        'turn_index' => 2, // Third turn
        'x_normalized' => 0.5,
        'y_normalized' => 0.5,
    ]);

    $component = Livewire::actingAs($user)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session]);

    $markers = $component->get('markers');
    expect($markers)->toHaveCount(1);

    $marker = $markers[0];
    expect($marker['turn_label'])->toBe('Beurt 3');
    expect($marker['color'])->toBeString();
    expect($marker['color'])->toMatch('/^#[0-9a-fA-F]{6}$/');
});

test('cannot delete shots when not editable', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $otherUser->id]);
    $shot = SessionShot::factory()->create([
        'session_id' => $session->id,
        'turn_index' => 0,
    ]);

    $component = Livewire::actingAs($user)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session]);

    // Should not be editable
    $component->assertSet('canEdit', false);

    // Try to delete the shot
    $component->call('deleteShot', $shot->id);

    // Shot should still exist
    $this->assertDatabaseHas('session_shots', ['id' => $shot->id]);
});

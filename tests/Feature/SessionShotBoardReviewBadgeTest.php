<?php

use App\Models\Session;
use App\Models\SessionTurnAnalysis;
use App\Models\User;
use Livewire\Livewire;

test('shot board shows a review badge when the current turn needs review', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);

    SessionTurnAnalysis::create([
        'session_id' => $session->id,
        'turn_index' => 0,
        'needs_review' => true,
        'review_reason' => 'Aantal gedetecteerd (4) wijkt af van het ingevulde aantal (5).',
        'detected_count' => 4,
        'overall_confidence' => 0.3,
    ]);

    Livewire::actingAs($user)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session])
        ->assertSet('currentTurnIndex', 0)
        ->assertSee('Controleren')
        ->assertSee('Aantal gedetecteerd (4) wijkt af van het ingevulde aantal (5).');
});

test('shot board shows no review badge when the turn analysis is clean', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);

    SessionTurnAnalysis::create([
        'session_id' => $session->id,
        'turn_index' => 0,
        'needs_review' => false,
        'detected_count' => 5,
        'overall_confidence' => 0.95,
    ]);

    Livewire::actingAs($user)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session])
        ->assertDontSee('Controleren');
});

test('upload modal exposes the expected shot count field', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);

    Livewire::actingAs($user)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session])
        ->assertSee('Aantal schoten deze beurt');
});

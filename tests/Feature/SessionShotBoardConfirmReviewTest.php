<?php

use App\Models\Session;
use App\Models\SessionTurnAnalysis;
use App\Models\User;

test('confirmTurnReview clears needs_review for the turn', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);
    SessionTurnAnalysis::create([
        'session_id' => $session->id, 'turn_index' => 0,
        'needs_review' => true, 'review_reason' => 'Lage zekerheid (40%).',
        'detected_count' => 5,
    ]);

    \Livewire\Livewire::actingAs($user)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session])
        ->call('confirmTurnReview', 0)
        ->assertNotified();

    expect(
        SessionTurnAnalysis::where('session_id', $session->id)->where('turn_index', 0)->first()->needs_review
    )->toBeFalse();
});

test('confirmTurnReview does nothing when the user cannot edit', function () {
    $owner = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $owner->id, 'target_type' => 'kkp_25m']);
    SessionTurnAnalysis::create([
        'session_id' => $session->id, 'turn_index' => 0,
        'needs_review' => true, 'detected_count' => 5,
    ]);

    $intruder = User::factory()->create();

    \Livewire\Livewire::actingAs($intruder)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session])
        ->call('confirmTurnReview', 0);

    expect(
        SessionTurnAnalysis::where('session_id', $session->id)->where('turn_index', 0)->first()->needs_review
    )->toBeTrue(); // still flagged — intruder cannot clear it
});

test('shot board shows a Bevestigd button when the turn needs review', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);
    SessionTurnAnalysis::create([
        'session_id' => $session->id, 'turn_index' => 0, 'needs_review' => true, 'detected_count' => 5,
    ]);

    \Livewire\Livewire::actingAs($user)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session])
        ->set('currentTurnIndex', 0)
        ->assertSee('Bevestigd');
});

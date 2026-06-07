<?php

use App\Models\Session;
use App\Models\SessionShot;
use App\Models\User;
use App\Services\Sessions\SessionShotService;

test('moveShot updates position, re-scores, and marks the shot as corrected', function () {
    $session = Session::factory()->create(['user_id' => User::factory()]);
    $shot = SessionShot::factory()->create([
        'session_id' => $session->id,
        'turn_index' => 0,
        'shot_index' => 1,
        'x_normalized' => 0.9,
        'y_normalized' => 0.9,
        'ring' => 1,
        'score' => 1,
        'source' => 'photo_detected',
    ]);

    $service = app(SessionShotService::class);
    $service->moveShot($shot, 0.5, 0.5); // exact centre

    $fresh = $shot->refresh();
    expect((float) $fresh->x_normalized)->toBe(0.5);
    expect((float) $fresh->y_normalized)->toBe(0.5);
    expect($fresh->ring)->toBe(10);          // centre scores 10 via ShotScoringService
    expect($fresh->score)->toBe(10);
    expect($fresh->source)->toBe('photo_corrected');
    expect((float) $fresh->distance_from_center)->toBeLessThan(0.001);
});

test('moveShot clamps out-of-range coordinates to [0,1]', function () {
    $session = Session::factory()->create(['user_id' => User::factory()]);
    $shot = SessionShot::factory()->create(['session_id' => $session->id, 'turn_index' => 0, 'shot_index' => 1]);

    app(SessionShotService::class)->moveShot($shot, 1.4, -0.3);

    $fresh = $shot->refresh();
    expect((float) $fresh->x_normalized)->toBe(1.0);
    expect((float) $fresh->y_normalized)->toBe(0.0);
    expect($fresh->source)->toBe('photo_corrected');
});

test('SessionShotBoard moveShot moves a shot belonging to the session', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);
    $shot = SessionShot::factory()->create([
        'session_id' => $session->id, 'turn_index' => 0, 'shot_index' => 1,
        'x_normalized' => 0.9, 'y_normalized' => 0.9, 'source' => 'photo_detected',
    ]);

    \Livewire\Livewire::actingAs($user)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session])
        ->call('moveShot', $shot->id, 0.5, 0.5);

    $fresh = $shot->refresh();
    expect((float) $fresh->x_normalized)->toBe(0.5);
    expect($fresh->ring)->toBe(10);
    expect($fresh->source)->toBe('photo_corrected');
});

test('SessionShotBoard moveShot ignores a shot from another session', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);
    $otherShot = SessionShot::factory()->create([
        'session_id' => Session::factory()->create()->id, 'turn_index' => 0, 'shot_index' => 1,
        'x_normalized' => 0.9, 'y_normalized' => 0.9,
    ]);

    \Livewire\Livewire::actingAs($user)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session])
        ->call('moveShot', $otherShot->id, 0.5, 0.5);

    expect((float) $otherShot->refresh()->x_normalized)->toBe(0.9); // unchanged
});

test('SessionShotBoard moveShot does nothing when the user cannot edit', function () {
    $owner = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $owner->id]);
    $shot = SessionShot::factory()->create([
        'session_id' => $session->id, 'turn_index' => 0, 'shot_index' => 1,
        'x_normalized' => 0.9, 'y_normalized' => 0.9, 'source' => 'photo_detected',
    ]);

    $intruder = User::factory()->create();

    \Livewire\Livewire::actingAs($intruder)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session])
        ->call('moveShot', $shot->id, 0.5, 0.5);

    $fresh = $shot->refresh();
    expect((float) $fresh->x_normalized)->toBe(0.9);   // unchanged
    expect($fresh->source)->toBe('photo_detected');     // not corrected
});

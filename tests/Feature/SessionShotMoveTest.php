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

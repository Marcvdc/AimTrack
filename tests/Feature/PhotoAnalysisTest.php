<?php

use App\Jobs\AnalyzeTurnPhotoJob;
use App\Models\Session;
use App\Models\SessionShot;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('user can upload photo for turn analysis', function () {
    Bus::fake();
    Storage::fake('private');

    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);

    Livewire::actingAs($user)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session])
        ->set('currentTurnIndex', 0)
        ->set('photo', UploadedFile::fake()->image('target.jpg', 800, 600))
        ->call('uploadPhoto');

    Bus::assertDispatched(AnalyzeTurnPhotoJob::class);
});

test('photo upload does not dispatch for all turns view', function () {
    Bus::fake();
    Storage::fake('private');

    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);

    Livewire::actingAs($user)
        ->test(\App\Livewire\SessionShotBoard::class, ['session' => $session])
        ->set('currentTurnIndex', \App\Livewire\SessionShotBoard::ALL_TURNS_VALUE)
        ->set('photo', UploadedFile::fake()->image('target.jpg'))
        ->call('uploadPhoto');

    Bus::assertNotDispatched(AnalyzeTurnPhotoJob::class);
});

test('analyze turn photo job persists v2 scoring from the service', function () {
    Storage::fake('private');

    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);
    $imagePath = 'session-photos/test-photo.jpg';
    Storage::disk('private')->put($imagePath, 'fake-image-content');

    Http::fake([
        '*/api/v2/analyze-target' => Http::response([
            'success' => true,
            'shots' => [
                ['x' => -0.2, 'y' => 0.3, 'ring' => 8, 'score' => 8, 'confidence' => 0.95, 'kind' => 'hole'],
                ['x' => 0.1, 'y' => -0.4, 'ring' => 7, 'score' => 7, 'confidence' => 0.87, 'kind' => 'hole'],
            ],
            'total_detected' => 2,
            'expected_shot_count' => 2,
            'detected_count' => 2,
            'count_matches_expected' => true,
            'overall_confidence' => 0.9,
            'needs_review' => false,
            'orientation_note' => '',
            'vision_model' => 'claude-opus-4-8',
            'calibration' => ['ok' => true, 'rms_error_mm' => 8.0, 'confidence' => 0.18, 'rings_detected' => 7, 'error' => null],
        ], 200),
    ]);

    (new AnalyzeTurnPhotoJob($session, 0, $imagePath, 2))->handle();

    $this->assertDatabaseHas('session_shots', [
        'session_id' => $session->id,
        'turn_index' => 0,
        'source' => 'photo_detected',
        'ring' => 8,
        'score' => 8,
    ]);

    $shot = SessionShot::where('session_id', $session->id)
        ->where('turn_index', 0)
        ->orderBy('shot_index')
        ->first();

    // canvas mapping: 0.5 + x * 0.46  ->  0.5 + (-0.2 * 0.46) = 0.408
    expect((float) $shot->x_normalized)->toBe(0.408);
    expect($shot->metadata)->toHaveKey('confidence', 0.95);
    expect($shot->metadata)->toHaveKey('photo_path', $imagePath);
});

test('analyze turn photo job does not create shots when the service fails', function () {
    Storage::fake('private');

    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);
    $imagePath = 'session-photos/test-photo.jpg';
    Storage::disk('private')->put($imagePath, 'fake-image-content');

    Http::fake([
        '*/api/v2/analyze-target' => Http::response(['error' => 'Processing failed'], 500),
    ]);

    // The job logs + marks itself failed on a 5xx; either way no shots are persisted.
    try {
        (new AnalyzeTurnPhotoJob($session, 0, $imagePath, 2))->handle();
    } catch (\Throwable $e) {
        // tolerated — the assertion below is the contract
    }

    $this->assertDatabaseMissing('session_shots', [
        'session_id' => $session->id,
        'turn_index' => 0,
        'source' => 'photo_detected',
    ]);
});

test('ring/score computation moved to the python service', function () {
    // Scoring is now discipline-correct and computed in python-service
    // (app/scoring.py, covered by python-service/tests/test_scoring.py).
    // The job no longer recomputes rings locally.
    expect(method_exists(AnalyzeTurnPhotoJob::class, 'calculateRing'))->toBeFalse();
    expect(method_exists(AnalyzeTurnPhotoJob::class, 'calculateScore'))->toBeFalse();
});

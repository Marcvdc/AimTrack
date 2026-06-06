<?php

use App\Jobs\AnalyzeTurnPhotoJob;
use App\Models\Session;
use App\Models\SessionShot;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
});

test('converts python coordinates from [-1,1] to [0,1] range', function () {
    // Arrange
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);

    // Create a fake image file
    $imagePath = 'session-photos/test.jpg';
    Storage::disk('private')->put($imagePath, 'fake-image-content');

    // Mock Python v2 service response with [-1, 1] coordinates
    Http::fake([
        '*/api/v2/analyze-target' => Http::response([
            'success' => true,
            'total_detected' => 3,
            'detected_count' => 3,
            'expected_shot_count' => 3,
            'count_matches_expected' => true,
            'overall_confidence' => 0.9,
            'needs_review' => false,
            'orientation_note' => 'ok',
            'vision_model' => 'claude-opus-4-8',
            'calibration' => ['ok' => true, 'rms_error_mm' => 8.2, 'confidence' => 0.18, 'rings_detected' => 7, 'error' => null],
            'shots' => [
                ['x' => -0.5, 'y' => 0.5, 'ring' => 6, 'score' => 6, 'confidence' => 0.95, 'kind' => 'hole'],  // Left-top quadrant
                ['x' => 0.5, 'y' => -0.5, 'ring' => 6, 'score' => 6, 'confidence' => 0.90, 'kind' => 'hole'],  // Right-bottom quadrant
                ['x' => 0.0, 'y' => 0.0, 'ring' => 10, 'score' => 10, 'confidence' => 0.85, 'kind' => 'hole'],   // Center
            ],
        ], 200),
    ]);

    // Act
    $job = new AnalyzeTurnPhotoJob($session, 0, $imagePath, 3);
    $job->handle();

    // Assert
    $shots = SessionShot::where('session_id', $session->id)
        ->where('turn_index', 0)
        ->where('source', 'photo_detected')
        ->get();

    expect($shots)->toHaveCount(3);

    // Check first shot: x=-0.5, y=0.5 maps via 0.46 ratio to x=0.27, y=0.73
    $shot1 = $shots->firstWhere('shot_index', 1);
    expect((float) $shot1->x_normalized)->toBe(0.27);
    expect((float) $shot1->y_normalized)->toBe(0.73);

    // Check second shot: x=0.5, y=-0.5 maps to x=0.73, y=0.27
    $shot2 = $shots->firstWhere('shot_index', 2);
    expect((float) $shot2->x_normalized)->toBe(0.73);
    expect((float) $shot2->y_normalized)->toBe(0.27);

    // Check third shot: x=0.0, y=0.0 maps to x=0.5, y=0.5 (center)
    $shot3 = $shots->firstWhere('shot_index', 3);
    expect((float) $shot3->x_normalized)->toBe(0.5);
    expect((float) $shot3->y_normalized)->toBe(0.5);
});

test('stores original python coordinates in metadata', function () {
    // Arrange
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);

    $imagePath = 'session-photos/test.jpg';
    Storage::disk('private')->put($imagePath, 'fake-image-content');

    Http::fake([
        '*/api/v2/analyze-target' => Http::response([
            'success' => true,
            'total_detected' => 1,
            'detected_count' => 1,
            'expected_shot_count' => 1,
            'count_matches_expected' => true,
            'overall_confidence' => 0.9,
            'needs_review' => false,
            'orientation_note' => 'ok',
            'vision_model' => 'claude-opus-4-8',
            'calibration' => ['ok' => true, 'rms_error_mm' => 8.2, 'confidence' => 0.18, 'rings_detected' => 7, 'error' => null],
            'shots' => [
                ['x' => -0.866, 'y' => 0.642, 'ring' => 2, 'score' => 2, 'confidence' => 0.892, 'kind' => 'hole'],
            ],
        ], 200),
    ]);

    // Act
    $job = new AnalyzeTurnPhotoJob($session, 0, $imagePath, 1);
    $job->handle();

    // Assert
    $shot = SessionShot::where('session_id', $session->id)->first();

    expect($shot->metadata)->toHaveKey('original_x');
    expect($shot->metadata)->toHaveKey('original_y');
    expect($shot->metadata['original_x'])->toBe(-0.866);
    expect($shot->metadata['original_y'])->toBe(0.642);
});

test('calculates distance from center correctly after conversion', function () {
    // Arrange
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);

    $imagePath = 'session-photos/test.jpg';
    Storage::disk('private')->put($imagePath, 'fake-image-content');

    Http::fake([
        '*/api/v2/analyze-target' => Http::response([
            'success' => true,
            'total_detected' => 1,
            'detected_count' => 1,
            'expected_shot_count' => 1,
            'count_matches_expected' => true,
            'overall_confidence' => 0.9,
            'needs_review' => false,
            'orientation_note' => 'ok',
            'vision_model' => 'claude-opus-4-8',
            'calibration' => ['ok' => true, 'rms_error_mm' => 8.2, 'confidence' => 0.18, 'rings_detected' => 7, 'error' => null],
            'shots' => [
                ['x' => 0.0, 'y' => 0.0, 'ring' => 10, 'score' => 10, 'confidence' => 0.95, 'kind' => 'hole'], // Center in Python coords
            ],
        ], 200),
    ]);

    // Act
    $job = new AnalyzeTurnPhotoJob($session, 0, $imagePath, 1);
    $job->handle();

    // Assert
    $shot = SessionShot::where('session_id', $session->id)->first();

    // Center shot should have 0 distance from center
    expect($shot->distance_from_center)->toBe(0.0);
});

test('persists ring and score returned by python', function () {
    // Arrange
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);

    $imagePath = 'session-photos/test.jpg';
    Storage::disk('private')->put($imagePath, 'fake-image-content');

    Http::fake([
        '*/api/v2/analyze-target' => Http::response([
            'success' => true,
            'total_detected' => 2,
            'detected_count' => 2,
            'expected_shot_count' => 2,
            'count_matches_expected' => true,
            'overall_confidence' => 0.9,
            'needs_review' => false,
            'orientation_note' => 'ok',
            'vision_model' => 'claude-opus-4-8',
            'calibration' => ['ok' => true, 'rms_error_mm' => 8.2, 'confidence' => 0.18, 'rings_detected' => 7, 'error' => null],
            'shots' => [
                ['x' => 0.0, 'y' => 0.0, 'ring' => 10, 'score' => 10, 'confidence' => 0.95, 'kind' => 'hole'],
                ['x' => 0.5, 'y' => 0.0, 'ring' => 7, 'score' => 7, 'confidence' => 0.80, 'kind' => 'hole'],
            ],
        ], 200),
    ]);

    // Act
    $job = new AnalyzeTurnPhotoJob($session, 0, $imagePath, 2);
    $job->handle();

    // Assert - ring/score come from Python, not recomputed locally
    $shots = SessionShot::where('session_id', $session->id)
        ->where('turn_index', 0)
        ->orderBy('shot_index')
        ->get();

    expect($shots[0]->ring)->toBe(10);
    expect($shots[0]->score)->toBe(10);
    expect($shots[1]->ring)->toBe(7);
    expect($shots[1]->score)->toBe(7);
});

test('removes existing photo detected shots before creating new ones', function () {
    // Arrange
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);

    // Create existing photo-detected shot
    SessionShot::factory()->create([
        'session_id' => $session->id,
        'turn_index' => 0,
        'source' => 'photo_detected',
    ]);

    $imagePath = 'session-photos/test.jpg';
    Storage::disk('private')->put($imagePath, 'fake-image-content');

    Http::fake([
        '*/api/v2/analyze-target' => Http::response([
            'success' => true,
            'total_detected' => 2,
            'detected_count' => 2,
            'expected_shot_count' => 2,
            'count_matches_expected' => true,
            'overall_confidence' => 0.9,
            'needs_review' => false,
            'orientation_note' => 'ok',
            'vision_model' => 'claude-opus-4-8',
            'calibration' => ['ok' => true, 'rms_error_mm' => 8.2, 'confidence' => 0.18, 'rings_detected' => 7, 'error' => null],
            'shots' => [
                ['x' => -0.5, 'y' => 0.5, 'ring' => 6, 'score' => 6, 'confidence' => 0.95, 'kind' => 'hole'],
                ['x' => 0.5, 'y' => -0.5, 'ring' => 6, 'score' => 6, 'confidence' => 0.90, 'kind' => 'hole'],
            ],
        ], 200),
    ]);

    // Act
    $job = new AnalyzeTurnPhotoJob($session, 0, $imagePath, 2);
    $job->handle();

    // Assert - should only have the 2 new shots
    $shots = SessionShot::where('session_id', $session->id)
        ->where('turn_index', 0)
        ->where('source', 'photo_detected')
        ->get();

    expect($shots)->toHaveCount(2);
});

test('sends correct request to python service', function () {
    // Arrange
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);

    $imagePath = 'session-photos/test.jpg';
    Storage::disk('private')->put($imagePath, 'fake-image-content');

    Http::fake();

    // Act
    $job = new AnalyzeTurnPhotoJob($session, 0, $imagePath, 5);

    try {
        $job->handle();
    } catch (\Exception $e) {
        // Expected to fail since we're not returning a proper response
    }

    // Assert
    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/api/v2/analyze-target')
            && $request->hasFile('file');
    });
});

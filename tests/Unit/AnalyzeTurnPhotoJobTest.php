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
    $session = Session::factory()->create(['user_id' => $user->id]);

    // Create a fake image file
    $imagePath = 'session-photos/test.jpg';
    Storage::disk('private')->put($imagePath, 'fake-image-content');

    // Mock Python service response with [-1, 1] coordinates
    Http::fake([
        '*/api/v1/analyze-target-v2' => Http::response([
            'success' => true,
            'total_detected' => 3,
            'shots' => [
                ['x' => -0.5, 'y' => 0.5, 'confidence' => 0.95],  // Left-top quadrant
                ['x' => 0.5, 'y' => -0.5, 'confidence' => 0.90],  // Right-bottom quadrant
                ['x' => 0.0, 'y' => 0.0, 'confidence' => 0.85],   // Center
            ],
        ], 200),
    ]);

    // Act
    $job = new AnalyzeTurnPhotoJob($session, 0, $imagePath);
    $job->handle();

    // Assert
    $shots = SessionShot::where('session_id', $session->id)
        ->where('turn_index', 0)
        ->where('source', 'photo_detected')
        ->get();

    expect($shots)->toHaveCount(3);

    // Check first shot: x=-0.5, y=0.5 should convert to x=0.25, y=0.75
    $shot1 = $shots->firstWhere('shot_index', 1);
    expect($shot1->x_normalized)->toBe(0.25);
    expect($shot1->y_normalized)->toBe(0.75);

    // Check second shot: x=0.5, y=-0.5 should convert to x=0.75, y=0.25
    $shot2 = $shots->firstWhere('shot_index', 2);
    expect($shot2->x_normalized)->toBe(0.75);
    expect($shot2->y_normalized)->toBe(0.25);

    // Check third shot: x=0.0, y=0.0 should convert to x=0.5, y=0.5 (center)
    $shot3 = $shots->firstWhere('shot_index', 3);
    expect($shot3->x_normalized)->toBe(0.5);
    expect($shot3->y_normalized)->toBe(0.5);
});

test('stores original python coordinates in metadata', function () {
    // Arrange
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);

    $imagePath = 'session-photos/test.jpg';
    Storage::disk('private')->put($imagePath, 'fake-image-content');

    Http::fake([
        '*/api/v1/analyze-target-v2' => Http::response([
            'success' => true,
            'total_detected' => 1,
            'shots' => [
                ['x' => -0.866, 'y' => 0.642, 'confidence' => 0.892],
            ],
        ], 200),
    ]);

    // Act
    $job = new AnalyzeTurnPhotoJob($session, 0, $imagePath);
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
    $session = Session::factory()->create(['user_id' => $user->id]);

    $imagePath = 'session-photos/test.jpg';
    Storage::disk('private')->put($imagePath, 'fake-image-content');

    Http::fake([
        '*/api/v1/analyze-target-v2' => Http::response([
            'success' => true,
            'total_detected' => 1,
            'shots' => [
                ['x' => 0.0, 'y' => 0.0, 'confidence' => 0.95], // Center in Python coords
            ],
        ], 200),
    ]);

    // Act
    $job = new AnalyzeTurnPhotoJob($session, 0, $imagePath);
    $job->handle();

    // Assert
    $shot = SessionShot::where('session_id', $session->id)->first();

    // Center shot should have 0 distance from center
    expect($shot->distance_from_center)->toBe(0.0);
});

test('removes existing photo detected shots before creating new ones', function () {
    // Arrange
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);

    // Create existing photo-detected shot
    SessionShot::factory()->create([
        'session_id' => $session->id,
        'turn_index' => 0,
        'source' => 'photo_detected',
    ]);

    $imagePath = 'session-photos/test.jpg';
    Storage::disk('private')->put($imagePath, 'fake-image-content');

    Http::fake([
        '*/api/v1/analyze-target-v2' => Http::response([
            'success' => true,
            'total_detected' => 2,
            'shots' => [
                ['x' => -0.5, 'y' => 0.5, 'confidence' => 0.95],
                ['x' => 0.5, 'y' => -0.5, 'confidence' => 0.90],
            ],
        ], 200),
    ]);

    // Act
    $job = new AnalyzeTurnPhotoJob($session, 0, $imagePath);
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
    $session = Session::factory()->create(['user_id' => $user->id]);

    $imagePath = 'session-photos/test.jpg';
    Storage::disk('private')->put($imagePath, 'fake-image-content');

    Http::fake();

    // Act
    $job = new AnalyzeTurnPhotoJob($session, 0, $imagePath);

    try {
        $job->handle();
    } catch (\Exception $e) {
        // Expected to fail since we're not returning a proper response
    }

    // Assert
    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/api/v1/analyze-target-v2')
            && $request->hasFile('file');
    });
});

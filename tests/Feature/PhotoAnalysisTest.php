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
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    // Mock the HTTP response from Python service
    Http::fake([
        config('services.image_processor.url').'/api/v1/analyze-target' => Http::response([
            'success' => true,
            'shots' => [
                ['x' => -0.2, 'y' => 0.3, 'confidence' => 0.95],
                ['x' => 0.1, 'y' => -0.4, 'confidence' => 0.87],
            ],
            'total_detected' => 2,
        ], 200),
    ]);

    // Mock the job dispatch
    Bus::fake();

    // Create a fake image
    $file = UploadedFile::fake()->image('target.jpg', 800, 600);

    // Test the photo upload action
    Livewire::test(\App\Livewire\SessionShotBoard::class, ['session' => $session])
        ->set('currentTurnIndex', 0)
        ->call('mountPhotoUploadAction')
        ->set('data.photo', $file)
        ->call('callAction', 'uploadPhoto');

    // Assert the job was dispatched
    Bus::assertDispatched(AnalyzeTurnPhotoJob::class, function ($job) use ($session) {
        return $job->session->id === $session->id
            && $job->turnIndex === 0
            && str_contains($job->photoPath, 'session-photos');
    });
});

test('photo upload is disabled for all turns view', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $component = Livewire::test(\App\Livewire\SessionShotBoard::class, ['session' => $session])
        ->set('currentTurnIndex', \App\Livewire\SessionShotBoard::ALL_TURNS_VALUE);

    // Assert the upload action is not visible
    $actions = $component->call('getFormActions');
    $uploadAction = collect($actions)->firstWhere('name', 'uploadPhoto');

    expect($uploadAction->isVisible())->toBeFalse();
});

test('analyze turn photo job processes image correctly', function () {
    Storage::fake('private');

    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);

    // Mock the HTTP response
    Http::fake([
        config('services.image_processor.url').'/api/v1/analyze-target' => Http::response([
            'success' => true,
            'shots' => [
                ['x' => -0.2, 'y' => 0.3, 'confidence' => 0.95],
                ['x' => 0.1, 'y' => -0.4, 'confidence' => 0.87],
            ],
            'total_detected' => 2,
        ], 200),
    ]);

    // Create a fake image
    $imagePath = 'session-photos/test-photo.jpg';
    Storage::disk('private')->put($imagePath, 'fake-image-content');

    // Dispatch the job
    $job = new AnalyzeTurnPhotoJob($session, 0, $imagePath);
    $job->handle();

    // Assert the shots were created
    $this->assertDatabaseHas('session_shots', [
        'session_id' => $session->id,
        'turn_index' => 0,
        'source' => 'photo_detected',
        'x_normalized' => -0.2,
        'y_normalized' => 0.3,
    ]);

    $this->assertDatabaseHas('session_shots', [
        'session_id' => $session->id,
        'turn_index' => 0,
        'source' => 'photo_detected',
        'x_normalized' => 0.1,
        'y_normalized' => -0.4,
    ]);

    // Check metadata contains confidence
    $shot = SessionShot::where('session_id', $session->id)
        ->where('turn_index', 0)
        ->where('x_normalized', -0.2)
        ->first();

    expect($shot->metadata)->toHaveKey('confidence', 0.95);
    expect($shot->metadata)->toHaveKey('photo_path', $imagePath);
});

test('analyze turn photo job handles service failure', function () {
    Storage::fake('private');

    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);

    // Mock a failed HTTP response
    Http::fake([
        config('services.image_processor.url').'/api/v1/analyze-target' => Http::response([
            'error' => 'Processing failed',
        ], 500),
    ]);

    // Create a fake image
    $imagePath = 'session-photos/test-photo.jpg';
    Storage::disk('private')->put($imagePath, 'fake-image-content');

    // Dispatch the job and expect it to fail
    $job = new AnalyzeTurnPhotoJob($session, 0, $imagePath);

    expect(fn () => $job->handle())->toThrow(Exception::class);

    // Assert no shots were created
    $this->assertDatabaseMissing('session_shots', [
        'session_id' => $session->id,
        'turn_index' => 0,
        'source' => 'photo_detected',
    ]);
});

test('coordinate normalization is correct', function () {
    // Test the ring calculation logic
    $job = new AnalyzeTurnPhotoJob(
        Session::factory()->create(),
        0,
        'test-path.jpg'
    );

    // Test bullseye (center)
    $ring = $job->calculateRing(0.0, 0.0);
    expect($ring)->toBe(10);

    // Test edge of ring 10
    $ring = $job->calculateRing(0.05, 0.0);
    expect($ring)->toBe(9);

    // Test miss (outside all rings)
    $ring = $job->calculateRing(0.5, 0.5);
    expect($ring)->toBe(0);
});

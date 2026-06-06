<?php

use App\Jobs\AnalyzeTurnPhotoJob;
use App\Models\Session;
use App\Models\SessionShot;
use App\Models\SessionTurnAnalysis;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
});

test('job calls v2 endpoint with target_type and expected count, persists returned scoring', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);
    $imagePath = 'session-photos/test.jpg';
    Storage::disk('private')->put($imagePath, 'fake-image-content');

    Http::fake([
        '*/api/v2/analyze-target' => Http::response([
            'success' => true,
            'shots' => [
                ['x' => 0.0, 'y' => 0.0, 'ring' => 10, 'score' => 10, 'confidence' => 0.95, 'kind' => 'hole'],
                ['x' => 0.5, 'y' => 0.0, 'ring' => 7, 'score' => 7, 'confidence' => 0.80, 'kind' => 'hole'],
            ],
            'total_detected' => 2,
            'expected_shot_count' => 2,
            'detected_count' => 2,
            'count_matches_expected' => true,
            'overall_confidence' => 0.9,
            'needs_review' => false,
            'orientation_note' => 'ok',
            'vision_model' => 'claude-opus-4-8',
            'calibration' => ['ok' => true, 'rms_error_mm' => 8.2, 'confidence' => 0.18, 'rings_detected' => 7, 'error' => null],
        ], 200),
    ]);

    (new AnalyzeTurnPhotoJob($session, 0, $imagePath, 2))->handle();

    $shots = SessionShot::where('session_id', $session->id)->where('turn_index', 0)->orderBy('shot_index')->get();
    expect($shots)->toHaveCount(2);
    expect($shots[0]->ring)->toBe(10);
    expect($shots[0]->score)->toBe(10);
    expect($shots[1]->ring)->toBe(7);
    expect((float) $shots[0]->x_normalized)->toBe(0.5);
    expect((float) $shots[1]->x_normalized)->toBe(0.75);
    expect($shots[0]->source)->toBe('photo_detected');
    expect($shots[0]->metadata['kind'])->toBe('hole');

    $analysis = SessionTurnAnalysis::where('session_id', $session->id)->where('turn_index', 0)->first();
    expect($analysis)->not->toBeNull();
    expect($analysis->needs_review)->toBeFalse();
    expect($analysis->detected_count)->toBe(2);
    expect($analysis->vision_model)->toBe('claude-opus-4-8');

    Http::assertSent(function (Request $request) {
        return str_contains($request->url(), '/api/v2/analyze-target')
            && $request->hasFile('file');
    });
});

test('job flags needs_review when python says so', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);
    $imagePath = 'session-photos/test.jpg';
    Storage::disk('private')->put($imagePath, 'x');

    Http::fake([
        '*/api/v2/analyze-target' => Http::response([
            'success' => true, 'shots' => [], 'total_detected' => 0,
            'expected_shot_count' => 5, 'detected_count' => 0, 'count_matches_expected' => false,
            'overall_confidence' => 0.0, 'needs_review' => true, 'orientation_note' => '',
            'vision_model' => 'claude-opus-4-8',
            'calibration' => ['ok' => false, 'rms_error_mm' => null, 'confidence' => null, 'rings_detected' => 1, 'error' => 'te weinig ringen'],
        ], 200),
    ]);

    (new AnalyzeTurnPhotoJob($session, 0, $imagePath, 5))->handle();

    $analysis = SessionTurnAnalysis::where('session_id', $session->id)->where('turn_index', 0)->first();
    expect($analysis->needs_review)->toBeTrue();
    expect($analysis->detected_count)->toBe(0);
});

test('job records review without calling python when target_type missing', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => null]);
    $imagePath = 'session-photos/test.jpg';
    Storage::disk('private')->put($imagePath, 'x');
    Http::fake();

    (new AnalyzeTurnPhotoJob($session, 0, $imagePath, 5))->handle();

    $analysis = SessionTurnAnalysis::where('session_id', $session->id)->where('turn_index', 0)->first();
    expect($analysis->needs_review)->toBeTrue();
    Http::assertNothingSent();
});

test('omits expected_shot_count from the request when it is null', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id, 'target_type' => 'kkp_25m']);
    $imagePath = 'session-photos/test.jpg';
    Storage::disk('private')->put($imagePath, 'x');

    Http::fake([
        '*/api/v2/analyze-target' => Http::response([
            'success' => true, 'shots' => [], 'total_detected' => 0,
            'expected_shot_count' => null, 'detected_count' => 0, 'count_matches_expected' => true,
            'overall_confidence' => 0.9, 'needs_review' => false, 'orientation_note' => '',
            'vision_model' => 'claude-opus-4-8',
            'calibration' => ['ok' => true, 'rms_error_mm' => 8.0, 'confidence' => 0.18, 'rings_detected' => 7, 'error' => null],
        ], 200),
    ]);

    (new AnalyzeTurnPhotoJob($session, 0, $imagePath, null))->handle();

    Http::assertSent(function (Request $request) {
        if (! str_contains($request->url(), '/api/v2/analyze-target')) {
            return false;
        }

        $names = collect($request->data())->pluck('name')->all();

        return ! in_array('expected_shot_count', $names, true)
            && in_array('target_type', $names, true);
    });
});

<?php

use App\Models\Session;
use App\Models\SessionTurnAnalysis;
use App\Models\User;

test('a turn analysis belongs to a session and casts its fields', function () {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);

    $analysis = SessionTurnAnalysis::create([
        'session_id' => $session->id,
        'turn_index' => 0,
        'needs_review' => true,
        'overall_confidence' => 0.42,
        'expected_shot_count' => 5,
        'detected_count' => 4,
        'count_matches_expected' => false,
        'calibration_rms_mm' => 8.2,
        'vision_model' => 'claude-opus-4-8',
        'analyzed_at' => now(),
    ]);

    $fresh = $analysis->refresh();
    expect($fresh->needs_review)->toBeTrue();
    expect($fresh->count_matches_expected)->toBeFalse();
    expect($fresh->overall_confidence)->toBe(0.42);
    expect($fresh->session->id)->toBe($session->id);
    expect($session->turnAnalyses()->count())->toBe(1);
});

test('turn analysis is unique per session and turn', function () {
    $session = Session::factory()->create();

    SessionTurnAnalysis::updateOrCreate(
        ['session_id' => $session->id, 'turn_index' => 0],
        ['needs_review' => true, 'detected_count' => 3]
    );
    SessionTurnAnalysis::updateOrCreate(
        ['session_id' => $session->id, 'turn_index' => 0],
        ['needs_review' => false, 'detected_count' => 5]
    );

    expect(SessionTurnAnalysis::where('session_id', $session->id)->count())->toBe(1);
    expect(SessionTurnAnalysis::where('session_id', $session->id)->first()->detected_count)->toBe(5);
});

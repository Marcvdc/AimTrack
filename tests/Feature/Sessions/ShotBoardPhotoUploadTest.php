<?php

declare(strict_types=1);

use App\Enums\TargetType;
use App\Jobs\AnalyzeTurnPhotoJob;
use App\Livewire\SessionShotBoard;
use App\Models\Session;
use App\Models\SessionTurnAnalysis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function boardSession(): Session
{
    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-user-key']);
    test()->actingAs($user);

    return Session::factory()->create([
        'user_id' => $user->id,
        'target_type' => TargetType::KKP_25M,
    ]);
}

test('een foto uploaden zet de analyse in de wachtrij en blokkeert het verzoek niet', function (): void {
    Queue::fake();
    Storage::fake('local');

    $session = boardSession();

    Livewire::test(SessionShotBoard::class, ['session' => $session])
        ->callAction('uploadTurnPhoto', [
            'photo' => UploadedFile::fake()->image('roos.jpg', 900, 900),
            'expected_shot_count' => 5,
        ])
        ->assertHasNoActionErrors()
        ->assertNotified();

    Queue::assertPushed(AnalyzeTurnPhotoJob::class, function (AnalyzeTurnPhotoJob $job) use ($session): bool {
        return $job->session->is($session)
            && $job->turnIndex === 0
            && $job->expectedShotCount === 5;
    });
});

test('de beurt staat meteen op wachten, zodat de schutter ziet dat er iets loopt', function (): void {
    Queue::fake();
    Storage::fake('local');

    $session = boardSession();

    Livewire::test(SessionShotBoard::class, ['session' => $session])
        ->callAction('uploadTurnPhoto', [
            'photo' => UploadedFile::fake()->image('roos.jpg', 900, 900),
            'expected_shot_count' => 5,
        ]);

    $analysis = SessionTurnAnalysis::where('session_id', $session->id)->first();

    expect($analysis->status)->toBe(SessionTurnAnalysis::STATUS_PENDING)
        ->and($analysis->needs_review)->toBeTrue()
        ->and($analysis->photo_path)->not->toBeNull();
});

test('het aantal schoten mag leeg blijven', function (): void {
    Queue::fake();
    Storage::fake('local');

    $session = boardSession();

    Livewire::test(SessionShotBoard::class, ['session' => $session])
        ->callAction('uploadTurnPhoto', [
            'photo' => UploadedFile::fake()->image('roos.jpg', 900, 900),
        ])
        ->assertHasNoActionErrors();

    Queue::assertPushed(AnalyzeTurnPhotoJob::class, fn (AnalyzeTurnPhotoJob $job): bool => $job->expectedShotCount === null);
});

test('zonder foto komt er geen job in de wachtrij', function (): void {
    Queue::fake();
    Storage::fake('local');

    Livewire::test(SessionShotBoard::class, ['session' => boardSession()])
        ->callAction('uploadTurnPhoto', ['expected_shot_count' => 5])
        ->assertHasActionErrors(['photo']);

    Queue::assertNothingPushed();
});

test('een beurt die gecontroleerd moet worden toont de melding op het bord', function (): void {
    $session = boardSession();

    SessionTurnAnalysis::create([
        'session_id' => $session->id,
        'turn_index' => 0,
        'status' => SessionTurnAnalysis::STATUS_DONE,
        'needs_review' => true,
        'review_reason' => 'Er zijn 4 schoten herkend terwijl je er 5 hebt ingevuld.',
    ]);

    Livewire::test(SessionShotBoard::class, ['session' => $session])
        ->assertSee('Controleren')
        ->assertSee('4 schoten herkend');
});

test('een bevestigde beurt toont geen melding meer', function (): void {
    $session = boardSession();

    SessionTurnAnalysis::create([
        'session_id' => $session->id,
        'turn_index' => 0,
        'status' => SessionTurnAnalysis::STATUS_DONE,
        'needs_review' => false,
    ]);

    Livewire::test(SessionShotBoard::class, ['session' => $session])
        ->assertDontSee('Controleren');
});

test('bevestigen zet de controlevlag weg', function (): void {
    $session = boardSession();

    SessionTurnAnalysis::create([
        'session_id' => $session->id,
        'turn_index' => 0,
        'status' => SessionTurnAnalysis::STATUS_DONE,
        'needs_review' => true,
        'review_reason' => 'Controleer de markers en bevestig de beurt.',
    ]);

    Livewire::test(SessionShotBoard::class, ['session' => $session])
        ->call('confirmTurnReview')
        ->assertNotified();

    expect(SessionTurnAnalysis::where('session_id', $session->id)->first()->needs_review)->toBeFalse();
});

test('een meekijker zonder rechten kan niets bevestigen', function (): void {
    $session = boardSession();

    SessionTurnAnalysis::create([
        'session_id' => $session->id,
        'turn_index' => 0,
        'status' => SessionTurnAnalysis::STATUS_DONE,
        'needs_review' => true,
        'review_reason' => 'Controleer de markers.',
    ]);

    Livewire::test(SessionShotBoard::class, ['session' => $session, 'readOnly' => true])
        ->call('confirmTurnReview');

    expect(SessionTurnAnalysis::where('session_id', $session->id)->first()->needs_review)->toBeTrue();
});

test('een mislukte analyse meldt dat de foto bewaard is', function (): void {
    $session = boardSession();

    SessionTurnAnalysis::create([
        'session_id' => $session->id,
        'turn_index' => 0,
        'status' => SessionTurnAnalysis::STATUS_FAILED,
        'needs_review' => true,
        'review_reason' => 'De foto-analyse is niet gelukt. Je foto is bewaard.',
    ]);

    Livewire::test(SessionShotBoard::class, ['session' => $session])
        ->assertSee('Mislukt')
        ->assertSee('Je foto is bewaard');
});

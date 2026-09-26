<?php

declare(strict_types=1);

use App\Enums\TargetType;
use App\Jobs\AnalyzeTurnPhotoJob;
use App\Models\Session;
use App\Models\SessionTurnAnalysis;
use App\Models\User;
use App\Services\Sessions\SessionShotService;
use App\Services\Sessions\TurnPhotoAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function photoSession(?TargetType $targetType = TargetType::KKP_25M): Session
{
    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-user-key']);

    return Session::factory()->create([
        'user_id' => $user->id,
        'target_type' => $targetType,
    ]);
}

function storeTurnPhoto(string $name = 'roos.jpg'): string
{
    Storage::fake('local');
    $file = UploadedFile::fake()->image($name, 900, 900);

    return $file->store('turn-photos', 'local');
}

function fakeTurnVision(array $shots, ?array $target = null, array $rejected = []): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 4000, 'output_tokens' => 9000],
            'content' => [['type' => 'text', 'text' => json_encode([
                'target' => $target ?? [
                    'center_x_px' => 450.0, 'center_y_px' => 450.0,
                    'outer_ring_number' => 1, 'outer_ring_radius_px' => 400.0,
                ],
                'shots' => $shots,
                'rejected' => $rejected,
                'orientation_note' => 'Kaart recht in beeld',
                'overall_confidence' => 0.7,
                'count_matches_expected' => true,
            ])]],
        ], 200),
    ]);
}

function shot(float $x, float $y, int $ring, float $confidence = 0.8): array
{
    return ['x_norm' => $x, 'y_norm' => $y, 'ring' => $ring, 'confidence' => $confidence, 'kind' => 'hole'];
}

test('een foto wordt omgezet naar schoten op het bord', function (): void {
    fakeTurnVision([shot(0.0, 0.0, 10), shot(0.5, 0.0, 5)]);

    $session = photoSession();
    $path = storeTurnPhoto();

    $analysis = app(TurnPhotoAnalysisService::class)->analyze($session, 0, $path, 2, 'local');

    expect($analysis->status)->toBe(SessionTurnAnalysis::STATUS_DONE)
        ->and($analysis->detected_count)->toBe(2)
        ->and($session->shots()->count())->toBe(2)
        ->and($session->shots()->pluck('source')->all())->toBe(['photo', 'photo']);
});

test('de positie komt in de bordconventie terecht', function (): void {
    fakeTurnVision([shot(0.0, 0.0, 10), shot(1.0, 0.0, 1)]);

    $session = photoSession();

    app(TurnPhotoAnalysisService::class)->analyze($session, 0, storeTurnPhoto(), 2, 'local');

    $shots = $session->shots()->orderBy('shot_index')->get();

    expect((float) $shots[0]->x_normalized)->toBe(0.5)
        ->and((float) $shots[0]->y_normalized)->toBe(0.5)
        ->and((float) $shots[1]->x_normalized)->toBe(1.0);
});

test('een kaart waarvan de buitenste ring een 6 is wordt naar binnen geschaald', function (): void {
    // Zonder de ring1-schaal zou dit schot op de rand van het bord belanden
    // in plaats van halverwege, precies de fout uit IMG_6462.
    fakeTurnVision([shot(1.0, 0.0, 6)], target: [
        'center_x_px' => 450.0, 'center_y_px' => 450.0,
        'outer_ring_number' => 6, 'outer_ring_radius_px' => 400.0,
    ]);

    $session = photoSession();

    app(TurnPhotoAnalysisService::class)->analyze($session, 0, storeTurnPhoto(), 1, 'local');

    expect((float) $session->shots()->first()->x_normalized)->toBe(0.75);
});

test('de afgelezen ring en de zekerheid blijven bewaard bij het schot', function (): void {
    fakeTurnVision([shot(0.2, 0.2, 8, 0.66)]);

    $session = photoSession();
    $path = storeTurnPhoto();

    app(TurnPhotoAnalysisService::class)->analyze($session, 0, $path, 1, 'local');

    $metadata = $session->shots()->first()->metadata;

    expect($metadata['read_ring'])->toBe(8)
        ->and($metadata['confidence'])->toBe(0.66)
        ->and($metadata['photo_path'])->toBe($path);
});

test('elke beurt uit een foto vraagt om controle', function (): void {
    fakeTurnVision([shot(0.1, 0.1, 9)]);

    $session = photoSession();

    $analysis = app(TurnPhotoAnalysisService::class)->analyze($session, 0, storeTurnPhoto(), 1, 'local');

    expect($analysis->needs_review)->toBeTrue()
        ->and($analysis->review_reason)->toContain('Controleer de markers');
});

test('een afwijkend aantal wordt met zoveel woorden gemeld', function (): void {
    fakeTurnVision([shot(0.1, 0.1, 9)]);

    $session = photoSession();

    $analysis = app(TurnPhotoAnalysisService::class)->analyze($session, 0, storeTurnPhoto(), 5, 'local');

    expect($analysis->review_reason)->toContain('1 schoten herkend terwijl je er 5 hebt ingevuld');
});

test('er wordt nooit aangevuld tot het ingevulde aantal', function (): void {
    fakeTurnVision([shot(0.1, 0.1, 9)]);

    $session = photoSession();

    app(TurnPhotoAnalysisService::class)->analyze($session, 0, storeTurnPhoto(), 5, 'local');

    expect($session->shots()->count())->toBe(1);
});

test('een te onzeker schot wordt niet geplaatst maar wel gemeld', function (): void {
    fakeTurnVision([shot(0.1, 0.1, 9, 0.9), shot(0.4, 0.4, 6, 0.10)]);

    $session = photoSession();

    $analysis = app(TurnPhotoAnalysisService::class)->analyze($session, 0, storeTurnPhoto(), null, 'local');

    expect($session->shots()->count())->toBe(1)
        ->and($analysis->dropped_low_confidence)->toBe(1)
        ->and($analysis->review_reason)->toContain('te onzeker');
});

test('een gat buiten de kaart wordt niet geplaatst maar wel gemeld', function (): void {
    /*
     * Ring 0 is het oordeel van het model dat er bij dit gat geen gedrukte ring
     * meer staat. Kwam dat toch op het bord, dan leidde de scoring een ring af uit
     * de afstand en werd een gat naast de kaart een treffer van 5.
     */
    fakeTurnVision([shot(0.1, 0.1, 9, 0.9), shot(0.84, -0.84, 0, 0.5)]);

    $session = photoSession();

    $analysis = app(TurnPhotoAnalysisService::class)->analyze($session, 0, storeTurnPhoto(), null, 'local');

    expect($session->shots()->count())->toBe(1)
        ->and($analysis->review_reason)->toContain('buiten de kaart');
});

test('opnieuw analyseren vervangt de vorige fotoschoten in plaats van ze te verdubbelen', function (): void {
    fakeTurnVision([shot(0.1, 0.1, 9), shot(0.2, 0.2, 8)]);

    $session = photoSession();
    app(TurnPhotoAnalysisService::class)->analyze($session, 0, storeTurnPhoto(), 2, 'local');

    fakeTurnVision([shot(0.3, 0.3, 7)]);
    app(TurnPhotoAnalysisService::class)->analyze($session, 0, storeTurnPhoto('tweede.jpg'), 1, 'local');

    expect($session->shots()->count())->toBe(1)
        ->and(SessionTurnAnalysis::where('session_id', $session->id)->count())->toBe(1);
});

test('handmatig geplaatste schoten blijven staan bij een nieuwe analyse', function (): void {
    $session = photoSession();
    app(SessionShotService::class)->recordShot($session, 0, 0.5, 0.5);

    fakeTurnVision([shot(0.1, 0.1, 9)]);
    app(TurnPhotoAnalysisService::class)->analyze($session, 0, storeTurnPhoto(), 1, 'local');

    expect($session->shots()->where('source', 'manual')->count())->toBe(1)
        ->and($session->shots()->count())->toBe(2);
});

test('een mislukte herkenning bewaart de foto en vraagt om aandacht', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response('stuk', 500)]);

    $session = photoSession();
    $path = storeTurnPhoto();

    $analysis = app(TurnPhotoAnalysisService::class)->analyze($session, 0, $path, 5, 'local');

    expect($analysis->status)->toBe(SessionTurnAnalysis::STATUS_FAILED)
        ->and($analysis->needs_review)->toBeTrue()
        ->and($analysis->photo_path)->toBe($path)
        ->and($analysis->review_reason)->toContain('Je foto is bewaard')
        ->and($session->shots()->count())->toBe(0);
});

test('een sessie zonder roostype valt terug op een standaard in plaats van te falen', function (): void {
    fakeTurnVision([shot(0.1, 0.1, 9)]);

    $session = photoSession(null);

    $analysis = app(TurnPhotoAnalysisService::class)->analyze($session, 0, storeTurnPhoto(), 1, 'local');

    expect($analysis->status)->toBe(SessionTurnAnalysis::STATUS_DONE);
});

test('de job zet de analyse in de wachtrij en niet in het verzoek', function (): void {
    Queue::fake();

    AnalyzeTurnPhotoJob::dispatch(photoSession(), 0, 'turn-photos/roos.jpg', 5);

    Queue::assertPushed(AnalyzeTurnPhotoJob::class, function (AnalyzeTurnPhotoJob $job): bool {
        return $job->turnIndex === 0 && $job->expectedShotCount === 5;
    });
});

test('een omgevallen job laat de beurt niet stil hangen', function (): void {
    $session = photoSession();

    (new AnalyzeTurnPhotoJob($session, 2, 'turn-photos/roos.jpg', 5))
        ->failed(new RuntimeException('wachtrij overleden'));

    $analysis = SessionTurnAnalysis::where('session_id', $session->id)->where('turn_index', 2)->first();

    expect($analysis)->not->toBeNull()
        ->and($analysis->status)->toBe(SessionTurnAnalysis::STATUS_FAILED)
        ->and($analysis->review_reason)->toContain('handmatig');
});

<?php

declare(strict_types=1);

use App\Enums\TargetType;
use App\Models\Session;
use App\Models\SessionTurnAnalysis;
use App\Models\User;
use App\Services\Vision\CorrectionExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function bevestigdeBeurt(bool $needsReview = false, bool $metFoto = true): Session
{
    $session = Session::factory()->create([
        'user_id' => User::factory()->create()->id,
        'target_type' => TargetType::KKG_50M,
    ]);

    $path = $metFoto
        ? UploadedFile::fake()->image('roos.jpg', 600, 600)->store('turn-photos', 'local')
        : 'turn-photos/weg.jpg';

    SessionTurnAnalysis::create([
        'session_id' => $session->id,
        'turn_index' => 0,
        'status' => SessionTurnAnalysis::STATUS_DONE,
        'needs_review' => $needsReview,
        'photo_path' => $path,
    ]);

    foreach ([['ring' => 10, 'source' => 'photo_corrected'], ['ring' => 8, 'source' => 'photo']] as $i => $data) {
        $session->shots()->create([
            'turn_index' => 0,
            'shot_index' => $i,
            'source' => $data['source'],
            'x_normalized' => 0.5,
            'y_normalized' => 0.5,
            'distance_from_center' => 0.0,
            'ring' => $data['ring'],
            'score' => $data['ring'],
        ]);
    }

    return $session;
}

beforeEach(fn () => Storage::fake('local'));

test('een bevestigde beurt levert een gelabelde foto op', function (): void {
    bevestigdeBeurt();

    $manifest = app(CorrectionExportService::class)->build();

    expect($manifest['photos'])->toHaveCount(1)
        ->and($manifest['photos'][0]['target_type'])->toBe('kkg_50m')
        ->and($manifest['photos'][0]['expected_shot_count'])->toBe(2)
        ->and($manifest['photos'][0]['truth_rings'])->toBe([10, 8]);
});

test('een beurt die nog gecontroleerd moet worden is geen grondwaarheid', function (): void {
    bevestigdeBeurt(needsReview: true);

    expect(app(CorrectionExportService::class)->build()['photos'])->toBe([]);
});

test('het aantal handmatige correcties wordt meegegeven', function (): void {
    bevestigdeBeurt();

    $bron = app(CorrectionExportService::class)->build()['photos'][0]['bron'];

    expect($bron['gecorrigeerd'])->toBe(1)
        ->and($bron['session_id'])->not->toBeNull();
});

test('een beurt waarvan de foto weg is wordt overgeslagen en gemeld', function (): void {
    bevestigdeBeurt(metFoto: false);

    $manifest = app(CorrectionExportService::class)->build();

    expect($manifest['photos'])->toBe([])
        ->and($manifest['overgeslagen'])->toHaveCount(1)
        ->and($manifest['overgeslagen'][0]['reden'])->toContain('niet meer aanwezig');
});

test('het manifest zegt er zelf bij dat het menselijke grondwaarheid is', function (): void {
    bevestigdeBeurt();

    expect(app(CorrectionExportService::class)->build()['_herkomst'])
        ->toContain('geen modeluitvoer');
});

test('het commando schrijft het manifest weg', function (): void {
    bevestigdeBeurt();
    $out = sys_get_temp_dir().'/aimtrack-export-'.bin2hex(random_bytes(4)).'/manifest.json';

    test()->artisan('aimtrack:meetset-exporteren', ['--out' => $out])
        ->expectsOutputToContain('1 foto(s) geëxporteerd')
        ->assertExitCode(0);

    expect(json_decode((string) file_get_contents($out), true)['photos'])->toHaveCount(1);
});

test('zonder bevestigde beurten wijst het commando de weg', function (): void {
    test()->artisan('aimtrack:meetset-exporteren')
        ->expectsOutputToContain('Bevestig eerst een paar beurten')
        ->assertExitCode(0);
});

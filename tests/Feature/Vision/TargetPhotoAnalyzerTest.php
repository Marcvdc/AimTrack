<?php

declare(strict_types=1);

use App\Enums\TargetType;
use App\Services\Vision\TargetPhotoAnalyzer;
use App\Services\Vision\VisionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function analyzerPhoto(): string
{
    $directory = sys_get_temp_dir().'/aimtrack-analyzer-'.bin2hex(random_bytes(4));
    mkdir($directory, 0o775, true);
    $path = $directory.'/roos.jpg';

    $image = imagecreatetruecolor(600, 600);
    imagefilledrectangle($image, 0, 0, 600, 600, imagecolorallocate($image, 230, 230, 225));
    imagefilledellipse($image, 300, 300, 200, 200, imagecolorallocate($image, 15, 15, 15));
    imagejpeg($image, $path, 90);
    imagedestroy($image);

    return $path;
}

/**
 * Bouwt een antwoord zoals Opus 5 het geeft: eerst een thinking-blok, daarna pas
 * het text-blok met de JSON. Dat de JSON niet in content[0] zit, is precies de val
 * waar ShooterCoach met zijn content.0.text in zou lopen.
 */
function fakeVisionResponse(array $payload, string $stopReason = 'end_turn'): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'stop_reason' => $stopReason,
            'content' => [
                ['type' => 'thinking', 'thinking' => ''],
                ['type' => 'text', 'text' => json_encode($payload)],
            ],
        ], 200),
    ]);
}

function completeVisionPayload(array $overrides = []): array
{
    return array_replace([
        'target' => ['center_x_px' => 300.0, 'center_y_px' => 300.0, 'outer_ring_number' => 1, 'outer_ring_radius_px' => 280.0],
        'shots' => [
            ['x_norm' => 0.1, 'y_norm' => -0.2, 'ring' => 9, 'confidence' => 0.91, 'kind' => 'hole'],
            ['x_norm' => -0.05, 'y_norm' => 0.05, 'ring' => 10, 'confidence' => 0.88, 'kind' => 'hole'],
        ],
        'rejected' => [
            ['x_px' => 120, 'y_px' => 430, 'kind' => 'paster', 'reason' => 'Gladde witte plakker'],
        ],
        'orientation_note' => 'Ringcijfers linksonder leesbaar',
        'overall_confidence' => 0.87,
        'count_matches_expected' => true,
    ], $overrides);
}

test('de analyse leest schoten, afwijzingen en de roos uit het antwoord', function (): void {
    fakeVisionResponse(completeVisionPayload());

    $result = TargetPhotoAnalyzer::make()->analyze(analyzerPhoto(), TargetType::KKP_25M, 2, 'sk-ant-test');

    expect($result->detectedCount())->toBe(2)
        ->and($result->rings())->toBe([9, 10])
        ->and($result->totalScore())->toBe(19)
        ->and($result->rejected)->toHaveCount(1)
        ->and($result->rejected[0]->kind)->toBe('paster')
        ->and($result->frame?->outerRingRadiusPixels)->toBe(280.0)
        ->and($result->frame?->outerRingNumber)->toBe(1)
        ->and($result->overallConfidence)->toBe(0.87)
        ->and($result->countMatchesExpected)->toBeTrue();
});

test('de foto gaat als base64 JPEG mee met het juiste model en schema', function (): void {
    fakeVisionResponse(completeVisionPayload());

    TargetPhotoAnalyzer::make()->analyze(analyzerPhoto(), TargetType::KKG_50M, 5, 'sk-ant-test');

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();
        $content = $body['messages'][0]['content'];

        return $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', 'sk-ant-test')
            && $request->hasHeader('anthropic-version', '2023-06-01')
            && $body['model'] === config('vision.model')
            && $body['thinking'] === ['type' => 'adaptive']
            && $body['output_config']['format']['type'] === 'json_schema'
            && $body['output_config']['format']['schema']['additionalProperties'] === false
            && in_array('outer_ring_number', $body['output_config']['format']['schema']['properties']['target']['required'], true)
            && $content[0]['type'] === 'image'
            && $content[0]['source']['media_type'] === 'image/jpeg'
            && base64_decode($content[0]['source']['data'], true) !== false;
    });
});

test('het verwachte aantal schoten staat in de systeemprompt zonder dwang', function (): void {
    fakeVisionResponse(completeVisionPayload());

    TargetPhotoAnalyzer::make()->analyze(analyzerPhoto(), TargetType::KKP_25M, 5, 'sk-ant-test');

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['system'];

        return str_contains($system, 'precies 5 schoten')
            && str_contains($system, 'NOOIT iets wat geen vers kogelgat is')
            && str_contains($system, '25m KKP');
    });
});

test('zonder verwacht aantal vraagt de prompt om alles waar het model zeker van is', function (): void {
    fakeVisionResponse(completeVisionPayload());

    TargetPhotoAnalyzer::make()->analyze(analyzerPhoto(), TargetType::GKG_100M, null, 'sk-ant-test');

    Http::assertSent(fn (Request $request): bool => str_contains(
        $request->data()['system'],
        'Het aantal schoten is onbekend',
    ));
});

test('waarden buiten bereik worden teruggebracht tot het geldige bereik', function (): void {
    fakeVisionResponse(completeVisionPayload([
        'shots' => [
            ['x_norm' => 0.2, 'y_norm' => 0.2, 'ring' => 14, 'confidence' => 1.7, 'kind' => 'hole'],
            ['x_norm' => 0.2, 'y_norm' => 0.2, 'ring' => -3, 'confidence' => -0.5, 'kind' => 'uncertain'],
        ],
    ]));

    $result = TargetPhotoAnalyzer::make()->analyze(analyzerPhoto(), TargetType::KKP_25M, 2, 'sk-ant-test');

    expect($result->shots[0]->ring)->toBe(10)
        ->and($result->shots[0]->confidence)->toBe(1.0)
        ->and($result->shots[1]->ring)->toBe(0)
        ->and($result->shots[1]->confidence)->toBe(0.0);
});

test('een roos zonder bruikbare straal levert geen frame op', function (): void {
    fakeVisionResponse(completeVisionPayload([
        'target' => ['center_x_px' => 300.0, 'center_y_px' => 300.0, 'outer_ring_number' => 1, 'outer_ring_radius_px' => 0.0],
    ]));

    $result = TargetPhotoAnalyzer::make()->analyze(analyzerPhoto(), TargetType::KKP_25M, 2, 'sk-ant-test');

    expect($result->frame)->toBeNull();
});

test('zonder Claude-key wordt er niets verstuurd', function (): void {
    Http::fake();

    try {
        TargetPhotoAnalyzer::make()->analyze(analyzerPhoto(), TargetType::KKP_25M, 5, null);
        $this->fail('Er had een VisionException moeten komen.');
    } catch (VisionException $exception) {
        expect($exception->getMessage())->toContain('Geen Claude-key');
    }

    Http::assertNothingSent();
});

test('een API-fout wordt met status gemeld', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response('overbelast', 529)]);

    TargetPhotoAnalyzer::make()->analyze(analyzerPhoto(), TargetType::KKP_25M, 5, 'sk-ant-test');
})->throws(VisionException::class, 'Beeldherkenning gaf HTTP 529');

test('een geweigerd verzoek wordt als weigering gemeld', function (): void {
    fakeVisionResponse(completeVisionPayload(), stopReason: 'refusal');

    TargetPhotoAnalyzer::make()->analyze(analyzerPhoto(), TargetType::KKP_25M, 5, 'sk-ant-test');
})->throws(VisionException::class, 'geweigerd door het model');

test('onleesbare JSON wordt als zodanig gemeld', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'dit is geen JSON']],
        ], 200),
    ]);

    TargetPhotoAnalyzer::make()->analyze(analyzerPhoto(), TargetType::KKP_25M, 5, 'sk-ant-test');
})->throws(VisionException::class, 'Ongeldige JSON');

test('een antwoord zonder tekstblok wordt gemeld', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'thinking', 'thinking' => '']],
        ], 200),
    ]);

    TargetPhotoAnalyzer::make()->analyze(analyzerPhoto(), TargetType::KKP_25M, 5, 'sk-ant-test');
})->throws(VisionException::class, 'Geen tekstantwoord');

test('de prompt vraagt expliciet om de buitenste gedrukte ring', function (): void {
    fakeVisionResponse(completeVisionPayload());

    TargetPhotoAnalyzer::make()->analyze(analyzerPhoto(), TargetType::KKP_25M, 5, 'sk-ant-test');

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['system'];

        return str_contains($system, 'BUITENSTE GEDRUKTE RING')
            && str_contains($system, 'lang niet altijd ring 1')
            && str_contains($system, 'Verwar die rand niet met de rand van het zwarte vlak');
    });
});

test('een kaart waarvan de buitenste ring een 6 is wordt als zodanig gelezen', function (): void {
    fakeVisionResponse(completeVisionPayload([
        'target' => ['center_x_px' => 564.0, 'center_y_px' => 733.0, 'outer_ring_number' => 6, 'outer_ring_radius_px' => 512.0],
    ]));

    $result = TargetPhotoAnalyzer::make()->analyze(analyzerPhoto(), TargetType::KKP_25M, 2, 'sk-ant-test');

    expect($result->frame?->outerRingNumber)->toBe(6)
        ->and($result->frame?->ring1Scale())->toBe(0.5);
});

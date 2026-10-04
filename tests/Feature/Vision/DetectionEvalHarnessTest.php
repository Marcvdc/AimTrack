<?php

declare(strict_types=1);

use App\Enums\TargetType;
use App\Services\Vision\DetectionEvalHarness;
use App\Services\Vision\DetectionMetrics;
use App\Services\Vision\ShotSelector;
use App\Services\Vision\TargetOverlayRenderer;
use App\Services\Vision\TargetPhotoAnalyzer;
use App\Services\Vision\TargetPhotoPreparer;
use Illuminate\Support\Facades\Http;

function harnessDirectory(): string
{
    $directory = sys_get_temp_dir().'/aimtrack-harness-'.bin2hex(random_bytes(4));
    mkdir($directory, 0o775, true);

    return $directory;
}

function harnessPhoto(string $directory, string $name = 'roos.jpg'): string
{
    $path = $directory.'/'.$name;
    $image = imagecreatetruecolor(800, 800);
    imagefilledrectangle($image, 0, 0, 800, 800, imagecolorallocate($image, 235, 235, 230));
    imagefilledellipse($image, 400, 400, 300, 300, imagecolorallocate($image, 18, 18, 18));
    imagejpeg($image, $path, 90);
    imagedestroy($image);

    return $path;
}

function buildHarness(): DetectionEvalHarness
{
    $preparer = new TargetPhotoPreparer(maxDimension: 1500, jpegQuality: 90);

    return new DetectionEvalHarness(
        analyzer: TargetPhotoAnalyzer::make(),
        selector: new ShotSelector(0.25),
        metrics: new DetectionMetrics,
        overlays: new TargetOverlayRenderer($preparer),
    );
}

function harnessVisionBody(array $shots): array
{
    return [
        'stop_reason' => 'end_turn',
        'content' => [[
            'type' => 'text',
            'text' => json_encode([
                'target' => ['center_x_px' => 400.0, 'center_y_px' => 400.0, 'outer_ring_number' => 1, 'outer_ring_radius_px' => 350.0],
                'shots' => $shots,
                'rejected' => [['x_px' => 100, 'y_px' => 120, 'kind' => 'paster', 'reason' => 'Witte plakker']],
                'orientation_note' => '',
                'overall_confidence' => 0.85,
                'count_matches_expected' => true,
            ]),
        ]],
    ];
}

test('de harness draait elke foto het gevraagde aantal keer en meet tegen de grondwaarheid', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response(harnessVisionBody([
            ['x_norm' => 0.1, 'y_norm' => 0.1, 'ring' => 9, 'confidence' => 0.9, 'kind' => 'hole'],
            ['x_norm' => -0.2, 'y_norm' => 0.0, 'ring' => 8, 'confidence' => 0.8, 'kind' => 'hole'],
        ]), 200),
    ]);

    $directory = harnessDirectory();

    $report = buildHarness()->run(
        entries: [[
            'path' => harnessPhoto($directory),
            'target_type' => TargetType::KKP_25M,
            'expected_shot_count' => 2,
            'truth_rings' => [9, 8],
        ]],
        apiKey: 'sk-ant-test',
        runs: 2,
        overlayDirectory: $directory.'/overlays',
    );

    expect($report['photos'])->toHaveCount(1)
        ->and($report['photos'][0]['runs'])->toHaveCount(2)
        ->and($report['photos'][0]['runs'][0]['selected'])->toBe(2)
        ->and($report['photos'][0]['runs'][0]['rejected_by_model'])->toBe(1)
        ->and($report['photos'][0]['runs'][0]['comparison']['count_correct'])->toBeTrue()
        ->and($report['photos'][0]['runs'][0]['comparison']['ring_mae'])->toBe(0.0)
        ->and($report['aggregate']['turns'])->toBe(2)
        ->and($report['aggregate']['count_accuracy'])->toBe(1.0)
        ->and($report['per_discipline']['kkp_25m']['turns'])->toBe(2);

    Http::assertSentCount(2);
});

test('de overlay van de eerste run wordt weggeschreven', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response(harnessVisionBody([
            ['x_norm' => 0.1, 'y_norm' => 0.1, 'ring' => 9, 'confidence' => 0.9, 'kind' => 'hole'],
        ]), 200),
    ]);

    $directory = harnessDirectory();

    $report = buildHarness()->run(
        entries: [[
            'path' => harnessPhoto($directory),
            'target_type' => TargetType::KKP_25M,
            'expected_shot_count' => 1,
            'truth_rings' => [9],
        ]],
        apiKey: 'sk-ant-test',
        runs: 1,
        overlayDirectory: $directory.'/overlays',
    );

    $overlay = $report['photos'][0]['runs'][0]['overlay'];

    expect($overlay)->toBe($directory.'/overlays/roos-overlay.jpg')
        ->and(file_exists($overlay))->toBeTrue()
        ->and(getimagesize($overlay)[2])->toBe(IMAGETYPE_JPEG);
});

test('een mislukte run breekt de meting niet af', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response('stuk', 500)]);

    $directory = harnessDirectory();

    $report = buildHarness()->run(
        entries: [[
            'path' => harnessPhoto($directory),
            'target_type' => TargetType::KKP_25M,
            'expected_shot_count' => 5,
            'truth_rings' => [9, 9, 8, 8, 7],
        ]],
        apiKey: 'sk-ant-test',
        runs: 1,
    );

    expect($report['photos'][0]['runs'][0])->toHaveKey('error')
        ->and($report['photos'][0]['runs'][0]['error'])->toContain('HTTP 500')
        ->and($report['aggregate']['turns'])->toBe(0);
});

test('een detectie die het verwachte aantal overschrijdt wordt afgekapt en gemeld', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response(harnessVisionBody([
            ['x_norm' => 0.1, 'y_norm' => 0.1, 'ring' => 9, 'confidence' => 0.95, 'kind' => 'hole'],
            ['x_norm' => 0.2, 'y_norm' => 0.2, 'ring' => 8, 'confidence' => 0.9, 'kind' => 'hole'],
            ['x_norm' => 0.3, 'y_norm' => 0.3, 'ring' => 6, 'confidence' => 0.5, 'kind' => 'uncertain'],
        ]), 200),
    ]);

    $directory = harnessDirectory();

    $report = buildHarness()->run(
        entries: [[
            'path' => harnessPhoto($directory),
            'target_type' => TargetType::KKP_25M,
            'expected_shot_count' => 2,
            'truth_rings' => [9, 8],
        ]],
        apiKey: 'sk-ant-test',
        runs: 1,
    );

    $run = $report['photos'][0]['runs'][0];

    expect($run['raw_detected'])->toBe(3)
        ->and($run['selected'])->toBe(2)
        ->and($run['selection']['dropped_over_count'])->toBe(1)
        ->and($run['selection']['dropped_low_confidence'])->toBe(0)
        ->and($run['rings'])->toBe([9, 8]);
});

test('zonder grondwaarheid wordt er niet vergeleken maar wel gemeten', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response(harnessVisionBody([
            ['x_norm' => 0.1, 'y_norm' => 0.1, 'ring' => 9, 'confidence' => 0.9, 'kind' => 'hole'],
        ]), 200),
    ]);

    $directory = harnessDirectory();

    $report = buildHarness()->run(
        entries: [[
            'path' => harnessPhoto($directory),
            'target_type' => TargetType::KKG_50M,
            'expected_shot_count' => null,
            'truth_rings' => null,
        ]],
        apiKey: 'sk-ant-test',
        runs: 2,
    );

    expect($report['photos'][0]['runs'][0])->not->toHaveKey('comparison')
        ->and($report['aggregate'])->toBe(['turns' => 0])
        ->and($report['photos'][0]['stability']['runs'])->toBe(2);
});

test('na elke foto wordt het rapport tot dan toe aangeboden', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response(harnessVisionBody([
            ['x_norm' => 0.1, 'y_norm' => 0.1, 'ring' => 9, 'confidence' => 0.9, 'kind' => 'hole'],
        ]), 200),
    ]);

    $directory = harnessDirectory();
    $tussenstanden = [];

    buildHarness()->run(
        entries: [
            ['path' => harnessPhoto($directory, 'een.jpg'), 'target_type' => TargetType::KKP_25M,
                'expected_shot_count' => 1, 'truth_rings' => [9]],
            ['path' => harnessPhoto($directory, 'twee.jpg'), 'target_type' => TargetType::KKP_25M,
                'expected_shot_count' => 1, 'truth_rings' => [9]],
        ],
        apiKey: 'sk-ant-test',
        runs: 1,
        onPhotoDone: function (array $partial) use (&$tussenstanden): void {
            $tussenstanden[] = count($partial['photos']);
        },
    );

    expect($tussenstanden)->toBe([1, 2]);
});

test('een tussenstand is een volledig bruikbaar rapport', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response(harnessVisionBody([
            ['x_norm' => 0.1, 'y_norm' => 0.1, 'ring' => 9, 'confidence' => 0.9, 'kind' => 'hole'],
        ]), 200),
    ]);

    $directory = harnessDirectory();
    $eerste = null;

    buildHarness()->run(
        entries: [
            ['path' => harnessPhoto($directory, 'een.jpg'), 'target_type' => TargetType::KKP_25M,
                'expected_shot_count' => 1, 'truth_rings' => [9]],
            ['path' => harnessPhoto($directory, 'twee.jpg'), 'target_type' => TargetType::KKP_25M,
                'expected_shot_count' => 1, 'truth_rings' => [9]],
        ],
        apiKey: 'sk-ant-test',
        runs: 1,
        onPhotoDone: function (array $partial) use (&$eerste): void {
            $eerste ??= $partial;
        },
    );

    expect($eerste['aggregate']['turns'])->toBe(1)
        ->and($eerste['per_discipline']['kkp_25m']['turns'])->toBe(1)
        ->and($eerste['photos'][0]['file'])->toBe('een.jpg');
});

test('al gemeten fotos worden bij hervatten overgeslagen', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response(harnessVisionBody([
            ['x_norm' => 0.1, 'y_norm' => 0.1, 'ring' => 9, 'confidence' => 0.9, 'kind' => 'hole'],
        ]), 200),
    ]);

    $directory = harnessDirectory();

    $report = buildHarness()->run(
        entries: [
            ['path' => harnessPhoto($directory, 'een.jpg'), 'target_type' => TargetType::KKP_25M,
                'expected_shot_count' => 1, 'truth_rings' => [9]],
            ['path' => harnessPhoto($directory, 'twee.jpg'), 'target_type' => TargetType::KKP_25M,
                'expected_shot_count' => 1, 'truth_rings' => [9]],
        ],
        apiKey: 'sk-ant-test',
        runs: 1,
        alreadyDone: [[
            'file' => 'een.jpg',
            'target_type' => 'kkp_25m',
            'expected_shot_count' => 1,
            'truth_rings' => [9],
            'runs' => [['run' => 1, 'selected' => 1, 'rings' => [9], 'rejected_by_model' => 0,
                'comparison' => ['count_correct' => true, 'count_delta' => 0, 'ring_accuracy' => 1.0, 'ring_mae' => 0.0]]],
            'stability' => ['runs' => 1, 'mean_spread' => null, 'max_spread' => null, 'count_spread' => 0],
        ]],
    );

    expect($report['photos'])->toHaveCount(2)
        ->and(array_column($report['photos'], 'file'))->toBe(['een.jpg', 'twee.jpg'])
        ->and($report['aggregate']['turns'])->toBe(2);

    // Alleen de tweede foto is opnieuw gemeten; de eerste kwam uit het bestaande rapport.
    Http::assertSentCount(1);
});

test('een schot onder de drempel wordt apart gerapporteerd en niet stil geslikt', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response(harnessVisionBody([
            ['x_norm' => 0.1, 'y_norm' => 0.1, 'ring' => 9, 'confidence' => 0.9, 'kind' => 'hole'],
            ['x_norm' => 0.4, 'y_norm' => 0.2, 'ring' => 7, 'confidence' => 0.12, 'kind' => 'uncertain'],
        ]), 200),
    ]);

    $directory = harnessDirectory();

    $report = buildHarness()->run(
        entries: [[
            'path' => harnessPhoto($directory),
            'target_type' => TargetType::KKP_25M,
            'expected_shot_count' => null,
            'truth_rings' => null,
        ]],
        apiKey: 'sk-ant-test',
        runs: 1,
    );

    $run = $report['photos'][0]['runs'][0];

    expect($run['selected'])->toBe(1)
        ->and($run['selection']['dropped_low_confidence'])->toBe(1)
        ->and($run['selection']['low_confidence_values'])->toBe([0.12]);
});

test('de ringcontrole gebruikt de buitenste ring die het model meldt', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => json_encode([
                'target' => ['center_x_px' => 400.0, 'center_y_px' => 400.0,
                    'outer_ring_number' => 6, 'outer_ring_radius_px' => 350.0],
                'shots' => [
                    ['x_norm' => 0.871, 'y_norm' => 0.0, 'ring' => 6, 'confidence' => 0.8, 'kind' => 'hole'],
                    ['x_norm' => 0.031, 'y_norm' => 0.0, 'ring' => 10, 'confidence' => 0.8, 'kind' => 'hole'],
                ],
                'rejected' => [],
                'orientation_note' => '',
                'overall_confidence' => 0.7,
                'count_matches_expected' => true,
            ])]],
        ], 200),
    ]);

    $directory = harnessDirectory();

    $report = buildHarness()->run(
        entries: [[
            'path' => harnessPhoto($directory),
            'target_type' => TargetType::KKP_25M,
            'expected_shot_count' => null,
            'truth_rings' => null,
        ]],
        apiKey: 'sk-ant-test',
        runs: 1,
    );

    $run = $report['photos'][0]['runs'][0];

    expect($run['frame']['outer_ring_number'])->toBe(6)
        ->and($run['frame']['ring1_scale'])->toBe(0.5)
        ->and($run['ring_position_agreement']['agree'])->toBe(2)
        ->and($run['ring_position_agreement']['max_abs_delta'])->toBe(0);
});

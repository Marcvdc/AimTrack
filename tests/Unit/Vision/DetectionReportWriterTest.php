<?php

declare(strict_types=1);

use App\Services\Vision\DetectionReportWriter;

function exampleReport(array $overrides = []): array
{
    return array_replace([
        'generated_at' => '2026-09-20T12:00:00+02:00',
        'runs_per_photo' => 3,
        'model' => 'claude-opus-5',
        'photos' => [[
            'file' => 'IMG_5672.jpg',
            'target_type' => 'kkp_25m',
            'expected_shot_count' => 5,
            'runs' => [
                ['run' => 1, 'selected' => 5, 'rejected_by_model' => 40, 'overlay' => '/tmp/overlay.jpg'],
                ['run' => 2, 'selected' => 4, 'rejected_by_model' => 38],
            ],
            'stability' => ['runs' => 2, 'mean_spread' => 0.06, 'max_spread' => 0.09, 'count_spread' => 1],
        ]],
        'aggregate' => [
            'turns' => 2,
            'count_accuracy' => 0.5,
            'mean_ring_accuracy' => 0.8,
            'mean_ring_mae' => 0.4,
            'over_count_turns' => 0,
            'under_count_turns' => 1,
        ],
        'per_discipline' => [
            'kkp_25m' => [
                'turns' => 2,
                'count_accuracy' => 0.5,
                'mean_ring_accuracy' => 0.8,
                'mean_ring_mae' => 0.4,
                'over_count_turns' => 0,
                'under_count_turns' => 1,
            ],
        ],
    ], $overrides);
}

test('het rapport noemt de kopcijfers en de foto', function (): void {
    $markdown = (new DetectionReportWriter)->toMarkdown(exampleReport());

    expect($markdown)->toContain('claude-opus-5')
        ->toContain('Aantal schoten exact goed: **50%**')
        ->toContain('Ringwaarde exact goed: **80%**')
        ->toContain('Gemiddelde ringafwijking (MAE): **0.4**')
        ->toContain('IMG_5672.jpg')
        ->toContain('`kkp_25m`')
        ->toContain('6% gem. / 9% max');
});

test('zonder grondwaarheid meldt het rapport dat alleen spreiding gemeten is', function (): void {
    $markdown = (new DetectionReportWriter)->toMarkdown(exampleReport([
        'aggregate' => ['turns' => 0],
        'per_discipline' => [],
        'photos' => [[
            'file' => 'IMG_1.jpg',
            'target_type' => 'kkp_25m',
            'expected_shot_count' => 5,
            'truth_rings' => null,
            'runs' => [['run' => 1, 'selected' => 5, 'rejected_by_model' => 3]],
            'stability' => ['runs' => 1, 'mean_spread' => null, 'max_spread' => null, 'count_spread' => 0],
        ]],
    ]));

    expect($markdown)->toContain('Zonder grondwaarheid in het manifest meet dit alleen spreiding');
});

test('mislukte runs worden niet verward met ontbrekende grondwaarheid', function (): void {
    $markdown = (new DetectionReportWriter)->toMarkdown(exampleReport([
        'aggregate' => ['turns' => 0],
        'per_discipline' => [],
        'photos' => [[
            'file' => 'IMG_1.jpg',
            'target_type' => 'kkp_25m',
            'expected_shot_count' => 3,
            'truth_rings' => [9, 8, 7],
            'runs' => [['run' => 1, 'error' => 'Beeldherkenning gaf HTTP 401']],
            'stability' => ['runs' => 0, 'mean_spread' => null, 'max_spread' => null, 'count_spread' => 0],
        ]],
    ]));

    expect($markdown)->toContain('geen enkele run is geslaagd')
        ->not->toContain('Zonder grondwaarheid in het manifest');
});

test('mislukte runs krijgen een eigen kopje', function (): void {
    $markdown = (new DetectionReportWriter)->toMarkdown(exampleReport([
        'photos' => [[
            'file' => 'IMG_9999.jpg',
            'target_type' => 'kkg_50m',
            'expected_shot_count' => 5,
            'runs' => [['run' => 1, 'error' => 'Beeldherkenning gaf HTTP 529']],
            'stability' => ['runs' => 0, 'mean_spread' => null, 'max_spread' => null, 'count_spread' => 0],
        ]],
    ]));

    expect($markdown)->toContain('## Mislukte runs')
        ->toContain('IMG_9999.jpg (run 1): Beeldherkenning gaf HTTP 529');
});

test('het rapport wordt als JSON en als Markdown weggeschreven', function (): void {
    $directory = sys_get_temp_dir().'/aimtrack-rapport-'.bin2hex(random_bytes(4));
    $writer = new DetectionReportWriter;

    $writer->writeJson(exampleReport(), $directory.'/rapport.json');
    $writer->writeMarkdown(exampleReport(), $directory.'/rapport.md');

    expect(json_decode((string) file_get_contents($directory.'/rapport.json'), true)['model'])->toBe('claude-opus-5')
        ->and(file_get_contents($directory.'/rapport.md'))->toContain('# Detectiemeting');
});

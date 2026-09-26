<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

function commandDirectory(): string
{
    $directory = sys_get_temp_dir().'/aimtrack-cmd-'.bin2hex(random_bytes(4));
    mkdir($directory.'/fotos', 0o775, true);
    mkdir($directory.'/uit', 0o775, true);

    return $directory;
}

function commandPhoto(string $directory, string $name): void
{
    $image = imagecreatetruecolor(400, 400);
    imagefilledrectangle($image, 0, 0, 400, 400, imagecolorallocate($image, 230, 230, 225));
    imagejpeg($image, $directory.'/fotos/'.$name, 90);
    imagedestroy($image);
}

function commandManifest(string $directory, array $files): string
{
    $path = $directory.'/manifest.json';
    file_put_contents($path, json_encode([
        'photos_dir' => 'fotos',
        'photos' => array_map(
            static fn (string $file): array => ['file' => $file, 'target_type' => 'kkp_25m'],
            $files,
        ),
    ]));

    return $path;
}

function fakeCommandVision(): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => json_encode([
                'target' => ['center_x_px' => 200.0, 'center_y_px' => 200.0, 'outer_ring_number' => 1, 'outer_ring_radius_px' => 180.0],
                'shots' => [['x_norm' => 0.1, 'y_norm' => 0.0, 'ring' => 9, 'confidence' => 0.9, 'kind' => 'hole']],
                'rejected' => [],
                'orientation_note' => '',
                'overall_confidence' => 0.8,
                'count_matches_expected' => true,
            ])]],
        ], 200),
    ]);
}

beforeEach(function (): void {
    config()->set('vision.api_key', 'sk-ant-test');
});

test('zonder key stopt het commando voordat er iets verstuurd wordt', function (): void {
    config()->set('vision.api_key', '');
    Http::fake();

    $directory = commandDirectory();
    commandPhoto($directory, 'een.jpg');

    $this->artisan('aimtrack:eval-detectie', [
        'manifest' => commandManifest($directory, ['een.jpg']),
        '--runs' => 1,
        '--force' => true,
        '--out' => $directory.'/uit',
    ])->assertExitCode(1);

    Http::assertNothingSent();
});

test('het rapport staat na afloop op schijf', function (): void {
    fakeCommandVision();

    $directory = commandDirectory();
    commandPhoto($directory, 'een.jpg');

    $this->artisan('aimtrack:eval-detectie', [
        'manifest' => commandManifest($directory, ['een.jpg']),
        '--runs' => 1,
        '--force' => true,
        '--no-overlays' => true,
        '--out' => $directory.'/uit',
    ])->assertExitCode(0);

    expect(file_exists($directory.'/uit/rapport.json'))->toBeTrue()
        ->and(file_exists($directory.'/uit/rapport.md'))->toBeTrue();
});

test('hervatten slaat geslaagde fotos over maar meet mislukte opnieuw', function (): void {
    fakeCommandVision();

    $directory = commandDirectory();
    commandPhoto($directory, 'geslaagd.jpg');
    commandPhoto($directory, 'mislukt.jpg');

    file_put_contents($directory.'/uit/rapport.json', json_encode([
        'photos' => [
            [
                'file' => 'geslaagd.jpg',
                'target_type' => 'kkp_25m',
                'runs' => [['run' => 1, 'selected' => 1, 'rings' => [9]]],
                'stability' => ['runs' => 1, 'mean_spread' => null, 'max_spread' => null, 'count_spread' => 0],
            ],
            [
                'file' => 'mislukt.jpg',
                'target_type' => 'kkp_25m',
                'runs' => [['run' => 1, 'error' => 'Netwerkfout bij de beeldherkenning']],
                'stability' => ['runs' => 0, 'mean_spread' => null, 'max_spread' => null, 'count_spread' => 0],
            ],
        ],
    ]));

    $this->artisan('aimtrack:eval-detectie', [
        'manifest' => commandManifest($directory, ['geslaagd.jpg', 'mislukt.jpg']),
        '--runs' => 1,
        '--force' => true,
        '--hervat' => true,
        '--no-overlays' => true,
        '--out' => $directory.'/uit',
    ])->assertExitCode(0);

    // Alleen de eerder mislukte foto gaat opnieuw naar het model.
    Http::assertSentCount(1);

    $report = json_decode((string) file_get_contents($directory.'/uit/rapport.json'), true);

    expect(array_column($report['photos'], 'file'))->toBe(['geslaagd.jpg', 'mislukt.jpg']);
});

test('is alles al gemeten dan wordt er niets verstuurd', function (): void {
    fakeCommandVision();

    $directory = commandDirectory();
    commandPhoto($directory, 'een.jpg');

    file_put_contents($directory.'/uit/rapport.json', json_encode([
        'photos' => [[
            'file' => 'een.jpg',
            'target_type' => 'kkp_25m',
            'runs' => [['run' => 1, 'selected' => 1, 'rings' => [9]]],
            'stability' => ['runs' => 1, 'mean_spread' => null, 'max_spread' => null, 'count_spread' => 0],
        ]],
    ]));

    $this->artisan('aimtrack:eval-detectie', [
        'manifest' => commandManifest($directory, ['een.jpg']),
        '--runs' => 1,
        '--force' => true,
        '--hervat' => true,
        '--out' => $directory.'/uit',
    ])->assertExitCode(0);

    Http::assertNothingSent();
});

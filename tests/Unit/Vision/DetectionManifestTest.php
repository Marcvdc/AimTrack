<?php

declare(strict_types=1);

use App\Enums\TargetType;
use App\Services\Vision\DetectionManifest;
use App\Services\Vision\VisionException;

function manifestDirectory(): string
{
    $directory = sys_get_temp_dir().'/aimtrack-manifest-'.bin2hex(random_bytes(4));
    mkdir($directory.'/fotos', 0o775, true);

    return $directory;
}

function placePhoto(string $directory, string $name): void
{
    $image = imagecreatetruecolor(50, 50);
    imagejpeg($image, $directory.'/fotos/'.$name, 80);
    imagedestroy($image);
}

function writeManifest(string $directory, array $payload): string
{
    $path = $directory.'/manifest.json';
    file_put_contents($path, json_encode($payload));

    return $path;
}

test('een manifest met truth_rings wordt volledig ingelezen', function (): void {
    $directory = manifestDirectory();
    placePhoto($directory, 'IMG_1.jpg');

    $path = writeManifest($directory, [
        'photos_dir' => 'fotos',
        'photos' => [[
            'file' => 'IMG_1.jpg',
            'target_type' => 'kkp_25m',
            'expected_shot_count' => 5,
            'truth_rings' => [9, 8, 8, 7, 6],
        ]],
    ]);

    $entries = (new DetectionManifest)->load($path);

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['target_type'])->toBe(TargetType::KKP_25M)
        ->and($entries[0]['expected_shot_count'])->toBe(5)
        ->and($entries[0]['truth_rings'])->toBe([9, 8, 8, 7, 6]);
});

test('het truth-formaat van de Python-service wordt ook geaccepteerd', function (): void {
    $directory = manifestDirectory();
    placePhoto($directory, 'IMG_2.jpg');

    $path = writeManifest($directory, [
        'photos_dir' => 'fotos',
        'photos' => [[
            'file' => 'IMG_2.jpg',
            'target_type' => 'kkg_50m',
            'truth' => [['ring' => 10], ['ring' => 9]],
        ]],
    ]);

    $entries = (new DetectionManifest)->load($path);

    expect($entries[0]['truth_rings'])->toBe([10, 9])
        ->and($entries[0]['expected_shot_count'])->toBeNull();
});

test('zonder grondwaarheid blijft truth_rings leeg', function (): void {
    $directory = manifestDirectory();
    placePhoto($directory, 'IMG_3.jpg');

    $path = writeManifest($directory, [
        'photos_dir' => 'fotos',
        'photos' => [['file' => 'IMG_3.jpg', 'target_type' => 'gkp_25m']],
    ]);

    expect((new DetectionManifest)->load($path)[0]['truth_rings'])->toBeNull();
});

test('een onbekend roostype noemt de geldige waarden', function (): void {
    $directory = manifestDirectory();
    placePhoto($directory, 'IMG_4.jpg');

    $path = writeManifest($directory, [
        'photos_dir' => 'fotos',
        'photos' => [['file' => 'IMG_4.jpg', 'target_type' => 'luchtpistool_10m']],
    ]);

    (new DetectionManifest)->load($path);
})->throws(VisionException::class, 'Onbekend roostype');

test('een ontbrekende foto wordt bij naam gemeld', function (): void {
    $directory = manifestDirectory();

    $path = writeManifest($directory, [
        'photos_dir' => 'fotos',
        'photos' => [['file' => 'weg.jpg', 'target_type' => 'kkp_25m']],
    ]);

    (new DetectionManifest)->load($path);
})->throws(VisionException::class, 'niet gevonden');

test('een manifest zonder photos-lijst wordt geweigerd', function (): void {
    $directory = manifestDirectory();
    $path = writeManifest($directory, ['photos' => []]);

    (new DetectionManifest)->load($path);
})->throws(VisionException::class, "bevat geen 'photos'-lijst");

test('een onleesbaar manifest wordt geweigerd', function (): void {
    (new DetectionManifest)->load('/bestaat/niet/manifest.json');
})->throws(VisionException::class, 'Manifest niet leesbaar');

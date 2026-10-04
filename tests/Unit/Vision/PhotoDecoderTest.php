<?php

declare(strict_types=1);

use App\Services\Vision\GdPhotoDecoder;
use App\Services\Vision\ImageMagickPhotoDecoder;
use App\Services\Vision\TargetPhotoPreparer;
use App\Services\Vision\VisionException;

function scratchDirectory(): string
{
    $directory = sys_get_temp_dir().'/aimtrack-vision-'.bin2hex(random_bytes(4));
    mkdir($directory, 0o775, true);

    return $directory;
}

function writeTestPhoto(string $path, int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 220, 220, 210));
    imagefilledellipse($image, (int) ($width / 2), (int) ($height / 2), (int) ($width / 3), (int) ($height / 3), imagecolorallocate($image, 20, 20, 20));
    imagejpeg($image, $path, 92);
    imagedestroy($image);

    return $path;
}

/**
 * Beide decoders moeten zich naar buiten toe hetzelfde gedragen, anders hangt het
 * gedrag van de applicatie af van wat er toevallig in het image zit.
 */
dataset('decoders', [
    'gd' => fn () => new GdPhotoDecoder,
    'imagemagick' => fn () => new ImageMagickPhotoDecoder,
]);

test('een te grote foto wordt verkleind tot de maximale langste zijde', function ($decoder): void {
    if (! $decoder->supported()) {
        $this->markTestSkipped(class_basename($decoder).' is hier niet beschikbaar.');
    }

    $path = writeTestPhoto(scratchDirectory().'/groot.jpg', 4000, 3000);
    $prepared = $decoder->decode($path, 1500, 90);

    expect($prepared->width)->toBe(1500)
        ->and($prepared->height)->toBe(1125)
        ->and($prepared->mediaType)->toBe('image/jpeg');
})->with('decoders');

test('een kleine foto wordt niet opgeblazen', function ($decoder): void {
    if (! $decoder->supported()) {
        $this->markTestSkipped(class_basename($decoder).' is hier niet beschikbaar.');
    }

    $path = writeTestPhoto(scratchDirectory().'/klein.jpg', 800, 600);
    $prepared = $decoder->decode($path, 1500, 90);

    expect($prepared->width)->toBe(800)
        ->and($prepared->height)->toBe(600);
})->with('decoders');

test('de uitvoer is een echte JPEG, ook als base64', function ($decoder): void {
    if (! $decoder->supported()) {
        $this->markTestSkipped(class_basename($decoder).' is hier niet beschikbaar.');
    }

    $path = writeTestPhoto(scratchDirectory().'/roos.jpg', 1000, 1000);
    $prepared = $decoder->decode($path, 1500, 90);
    $decoded = base64_decode($prepared->toBase64(), true);

    expect($decoded)->toBe($prepared->binary)
        ->and(getimagesizefromstring($decoded)[2])->toBe(IMAGETYPE_JPEG);
})->with('decoders');

test('een ontbrekend bestand geeft een duidelijke fout', function ($decoder): void {
    if (! $decoder->supported()) {
        $this->markTestSkipped(class_basename($decoder).' is hier niet beschikbaar.');
    }

    expect(fn () => $decoder->decode('/bestaat/echt/niet.jpg', 1500, 90))
        ->toThrow(VisionException::class, 'niet leesbaar');
})->with('decoders');

test('een bestand dat geen afbeelding is geeft een duidelijke fout', function ($decoder): void {
    if (! $decoder->supported()) {
        $this->markTestSkipped(class_basename($decoder).' is hier niet beschikbaar.');
    }

    $path = scratchDirectory().'/geen-plaatje.jpg';
    file_put_contents($path, 'dit is gewoon tekst');

    expect(fn () => $decoder->decode($path, 1500, 90))->toThrow(VisionException::class);
})->with('decoders');

test('de GD-fout wijst de weg naar ImageMagick, want GD leest geen HEIC', function (): void {
    $path = scratchDirectory().'/nep.heic';
    file_put_contents($path, "\x00\x00\x00\x18ftypheic");

    expect(fn () => (new GdPhotoDecoder)->decode($path, 1500, 90))
        ->toThrow(VisionException::class, 'GD leest geen HEIC');
});

test('de preparer kiest ImageMagick zodra dat beschikbaar is', function (): void {
    $verwacht = (new ImageMagickPhotoDecoder)->supported() ? 'ImageMagickPhotoDecoder' : 'GdPhotoDecoder';

    expect((new TargetPhotoPreparer)->decoderNaam())->toBe($verwacht);
});

test('een expliciet meegegeven decoder wint van de automatische keuze', function (): void {
    expect((new TargetPhotoPreparer(decoder: new GdPhotoDecoder))->decoderNaam())
        ->toBe('GdPhotoDecoder');
});

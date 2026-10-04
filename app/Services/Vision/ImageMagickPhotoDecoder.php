<?php

namespace App\Services\Vision;

use Illuminate\Support\Facades\Process;

/**
 * Decodeert met ImageMagick in een apart proces.
 *
 * Dit is om twee redenen het voorkeurspad, en die hangen samen.
 *
 * HEIC. Een iPhone levert standaard HEIC en GD kan dat formaat niet lezen, dus
 * zonder deze decoder strandt vrijwel elke upload vanaf een telefoon. ImageMagick
 * op Debian bookworm leest het via libheif zonder verder werk.
 *
 * Geheugen. GD pakt een 12MP-foto uit tot ruim 48 MB aan bitmap en houdt tijdens
 * het verkleinen origineel en kopie tegelijk vast; gemeten piek was 139 MB tegen
 * een container die op 128 MB staat. Hier gebeurt het uitpakken in een apart
 * proces, dus PHP's memory_limit speelt geen rol meer. PHP ziet alleen nog de
 * verkleinde JPEG van een paar honderd kilobyte en leest de afmetingen uit de
 * header, zonder ooit de pixels te decoderen.
 */
class ImageMagickPhotoDecoder implements PhotoDecoder
{
    public function __construct(
        private readonly ?string $binary = null,
        private readonly int $timeout = 60,
        private readonly string $memoryLimit = '256MiB',
    ) {}

    public function supported(): bool
    {
        return $this->binary() !== null;
    }

    public function decode(string $path, int $maxDimension, int $jpegQuality): PreparedPhoto
    {
        $binary = $this->binary();

        if ($binary === null) {
            throw new VisionException('ImageMagick is niet beschikbaar in deze omgeving.');
        }

        if (! is_readable($path)) {
            throw new VisionException("Foto niet leesbaar: {$path}");
        }

        $result = Process::timeout($this->timeout)->run([
            $binary,
            '-limit', 'memory', $this->memoryLimit,
            '-limit', 'map', $this->memoryLimit,
            /*
             * [0] pakt de eerste frame. Een HEIC van een iPhone kan meerdere frames
             * bevatten (live photo, diepte-kaart); zonder de index levert ImageMagick
             * dan meerdere JPEG's achter elkaar op stdout.
             */
            $path.'[0]',
            '-auto-orient',
            // Alleen verkleinen, nooit oprekken. Dat is wat de '>' doet.
            '-resize', $maxDimension.'x'.$maxDimension.'>',
            // iPhone-foto's staan vaak in Display P3; gelijktrekken naar sRGB.
            '-colorspace', 'sRGB',
            '-strip',
            '-quality', (string) $jpegQuality,
            'jpg:-',
        ]);

        if (! $result->successful()) {
            throw new VisionException(sprintf(
                'ImageMagick kon %s niet omzetten: %s',
                basename($path),
                trim(mb_substr($result->errorOutput(), 0, 300)),
            ));
        }

        $binaryOutput = $result->output();

        if ($binaryOutput === '') {
            throw new VisionException("ImageMagick leverde een lege afbeelding voor {$path}.");
        }

        $size = @getimagesizefromstring($binaryOutput);

        if ($size === false) {
            throw new VisionException("ImageMagick leverde geen leesbare JPEG voor {$path}.");
        }

        return new PreparedPhoto(
            binary: $binaryOutput,
            mediaType: 'image/jpeg',
            width: $size[0],
            height: $size[1],
        );
    }

    /**
     * ImageMagick 7 heet `magick`, versie 6 `convert`. Debian bookworm levert 6.
     */
    private function binary(): ?string
    {
        if ($this->binary !== null) {
            return $this->binary;
        }

        foreach (['magick', 'convert'] as $kandidaat) {
            if ($this->onPath($kandidaat)) {
                return $kandidaat;
            }
        }

        return null;
    }

    private function onPath(string $binary): bool
    {
        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directory) {
            if ($directory !== '' && is_executable(rtrim($directory, '/').'/'.$binary)) {
                return true;
            }
        }

        return false;
    }
}

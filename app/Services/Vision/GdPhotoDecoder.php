<?php

namespace App\Services\Vision;

use GdImage;

/**
 * Decodeert met de ingebouwde GD-extensie.
 *
 * Dit is de terugval voor omgevingen zonder ImageMagick. Twee beperkingen die je
 * moet kennen: GD leest geen HEIC, dus een foto rechtstreeks van een iPhone werkt
 * hier niet, en het uitpakken gebeurt binnen het PHP-geheugen. Een 12MP-foto piekt
 * daardoor rond de 100 MB bovenop de applicatie zelf, wat boven de standaard
 * memory_limit van 128 MB uitkomt.
 */
class GdPhotoDecoder implements PhotoDecoder
{
    public function supported(): bool
    {
        return function_exists('imagecreatefromstring');
    }

    public function decode(string $path, int $maxDimension, int $jpegQuality): PreparedPhoto
    {
        if (! is_readable($path)) {
            throw new VisionException("Foto niet leesbaar: {$path}");
        }

        $contents = file_get_contents($path);

        if ($contents === false || $contents === '') {
            throw new VisionException("Foto is leeg of onleesbaar: {$path}");
        }

        $image = @imagecreatefromstring($contents);

        if (! $image instanceof GdImage) {
            throw new VisionException(
                "Niet-ondersteund of beschadigd beeldformaat: {$path}. "
                .'GD leest geen HEIC; installeer ImageMagick om telefoonfoto\'s te kunnen verwerken.'
            );
        }

        unset($contents);

        $image = $this->applyExifOrientation($image, $path);
        $image = $this->downscale($image, $maxDimension);

        $width = imagesx($image);
        $height = imagesy($image);

        ob_start();
        $encoded = imagejpeg($image, null, $jpegQuality);
        $binary = (string) ob_get_clean();

        imagedestroy($image);

        if (! $encoded || $binary === '') {
            throw new VisionException("JPEG-codering mislukt voor: {$path}");
        }

        return new PreparedPhoto(
            binary: $binary,
            mediaType: 'image/jpeg',
            width: $width,
            height: $height,
        );
    }

    private function applyExifOrientation(GdImage $image, string $path): GdImage
    {
        $orientation = $this->readOrientation($path);

        if ($orientation === null || $orientation === 1) {
            return $image;
        }

        $rotate = static function (GdImage $img, float $angle): GdImage {
            $rotated = imagerotate($img, $angle, 0);

            if (! $rotated instanceof GdImage) {
                return $img;
            }

            imagedestroy($img);

            return $rotated;
        };

        return match ($orientation) {
            2 => tap($image, static fn (GdImage $img) => imageflip($img, IMG_FLIP_HORIZONTAL)),
            3 => $rotate($image, 180),
            4 => tap($image, static fn (GdImage $img) => imageflip($img, IMG_FLIP_VERTICAL)),
            5 => tap($rotate($image, -90), static fn (GdImage $img) => imageflip($img, IMG_FLIP_HORIZONTAL)),
            6 => $rotate($image, -90),
            7 => tap($rotate($image, 90), static fn (GdImage $img) => imageflip($img, IMG_FLIP_HORIZONTAL)),
            8 => $rotate($image, 90),
            default => $image,
        };
    }

    private function readOrientation(string $path): ?int
    {
        if (! function_exists('exif_read_data')) {
            return null;
        }

        $exif = @exif_read_data($path);

        if (! is_array($exif) || ! isset($exif['Orientation'])) {
            return null;
        }

        return (int) $exif['Orientation'];
    }

    private function downscale(GdImage $image, int $maxDimension): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest <= $maxDimension) {
            return $image;
        }

        $scale = $maxDimension / $longest;
        $scaled = imagescale($image, (int) round($width * $scale), (int) round($height * $scale), IMG_BICUBIC);

        if (! $scaled instanceof GdImage) {
            return $image;
        }

        imagedestroy($image);

        return $scaled;
    }
}

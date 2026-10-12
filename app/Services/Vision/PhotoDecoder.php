<?php

namespace App\Services\Vision;

/**
 * Zet een foto van schijf om naar een rechtgezette, verkleinde JPEG.
 *
 * Er zijn twee implementaties omdat ze verschillende dingen kunnen. Zie
 * ImageMagickPhotoDecoder (kan HEIC, decodeert buiten het PHP-geheugen) en
 * GdPhotoDecoder (altijd beschikbaar, maar geen HEIC en zwaar op het geheugen).
 */
interface PhotoDecoder
{
    public function supported(): bool;

    /**
     * @throws VisionException
     */
    public function decode(string $path, int $maxDimension, int $jpegQuality): PreparedPhoto;
}

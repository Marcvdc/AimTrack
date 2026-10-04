<?php

namespace App\Services\Vision;

/**
 * Een roosfoto, rechtgezet en verkleind, klaar om naar het model te sturen.
 */
final readonly class PreparedPhoto
{
    public function __construct(
        public string $binary,
        public string $mediaType,
        public int $width,
        public int $height,
    ) {}

    public function toBase64(): string
    {
        return base64_encode($this->binary);
    }
}

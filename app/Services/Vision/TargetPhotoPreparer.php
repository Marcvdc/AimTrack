<?php

namespace App\Services\Vision;

/**
 * Zet een roosfoto klaar voor het vision-model: rechtzetten, verkleinen, als JPEG.
 *
 * Rechtzetten is geen cosmetiek. Een telefoonfoto staat staand in de exif maar
 * liggend in de pixels, en een model dat de roos gekanteld ziet, leest de ringen
 * scheef af. Verkleinen raakt de meetwaarden niet, omdat het model de posities
 * genormaliseerd teruggeeft ten opzichte van de roos zelf; het begrenst alleen de
 * tokenkosten.
 *
 * Het echte werk doet een PhotoDecoder. ImageMagick heeft de voorkeur omdat het
 * HEIC leest en buiten het PHP-geheugen decodeert; GD is de terugval en kan geen
 * van beide.
 */
class TargetPhotoPreparer
{
    private readonly PhotoDecoder $decoder;

    public function __construct(
        private readonly int $maxDimension = 1500,
        private readonly int $jpegQuality = 90,
        ?PhotoDecoder $decoder = null,
    ) {
        $this->decoder = $decoder ?? $this->bestBeschikbare();
    }

    /**
     * @throws VisionException
     */
    public function prepare(string $path): PreparedPhoto
    {
        return $this->decoder->decode($path, $this->maxDimension, $this->jpegQuality);
    }

    /**
     * Welke decoder er daadwerkelijk gebruikt wordt. Handig om in een rapport of
     * een foutmelding te kunnen noemen, want het verschil bepaalt of HEIC werkt.
     */
    public function decoderNaam(): string
    {
        return class_basename($this->decoder);
    }

    private function bestBeschikbare(): PhotoDecoder
    {
        $imagemagick = new ImageMagickPhotoDecoder;

        return $imagemagick->supported() ? $imagemagick : new GdPhotoDecoder;
    }
}

<?php

namespace App\Services\Vision;

use GdImage;

/**
 * Tekent de uitkomst van een analyse over de foto heen, zodat een mens in een
 * oogopslag ziet of de detectie klopt.
 *
 * Kleurcodering, gelijk aan de visuele bewijsvoering van 18 juli:
 * rood    = geteld als vers schot
 * oranje  = wel gezien, maar afgevallen op zekerheid of op het maximale aantal
 * cyaan   = bewust afgewezen (plakker, gedrukt cijfer, scheur, tape)
 * groen   = de roos zoals het model hem ziet (midden plus rand van de buitenste
 *           gedrukte ring, met het ringnummer erbij)
 *
 * De oranje categorie is het belangrijkst bij het afstellen: dat zijn de gaten die
 * het model wel zag maar die de drempel wegnam.
 */
class TargetOverlayRenderer
{
    public function __construct(
        private readonly TargetPhotoPreparer $preparer,
    ) {}

    /**
     * @param  list<DetectedShot>  $selected  de schoten die na de zeef overblijven
     *
     * @throws VisionException
     */
    public function render(
        string $imagePath,
        VisionAnalysisResult $result,
        array $selected,
        string $outputPath,
    ): void {
        $photo = $this->preparer->prepare($imagePath);
        $image = @imagecreatefromstring($photo->binary);

        if (! $image instanceof GdImage) {
            throw new VisionException("Overlay kon de voorbewerkte foto niet openen: {$imagePath}");
        }

        $red = imagecolorallocate($image, 255, 40, 40);
        $orange = imagecolorallocate($image, 255, 170, 0);
        $cyan = imagecolorallocate($image, 0, 220, 230);
        $green = imagecolorallocate($image, 60, 230, 120);

        $frame = $result->frame;

        if ($frame !== null) {
            $this->drawTargetFrame($image, $frame, $green);
        }

        $radius = $frame !== null
            ? max(8, (int) round($frame->outerRingRadiusPixels * 0.06))
            : max(8, (int) round(min($photo->width, $photo->height) * 0.02));

        foreach ($result->rejected as $candidate) {
            $this->drawMarker($image, $candidate->xPixels, $candidate->yPixels, $radius, $cyan, $candidate->kind);
        }

        if ($frame !== null) {
            $selectedKeys = $this->keys($selected);
            $index = 0;

            foreach ($result->shots as $shot) {
                $index++;
                $point = $frame->toPixels($shot->xNormalized, $shot->yNormalized);
                $isSelected = in_array($this->key($shot), $selectedKeys, true);

                $this->drawMarker(
                    $image,
                    (int) round($point['x']),
                    (int) round($point['y']),
                    $radius,
                    $isSelected ? $red : $orange,
                    sprintf('%d r%d %.2f', $index, $shot->ring, $shot->confidence),
                );
            }
        }

        $directory = dirname($outputPath);

        if (! is_dir($directory) && ! mkdir($directory, 0o775, true) && ! is_dir($directory)) {
            imagedestroy($image);

            throw new VisionException("Kon de overlay-map niet aanmaken: {$directory}");
        }

        $written = imagejpeg($image, $outputPath, 90);
        imagedestroy($image);

        if (! $written) {
            throw new VisionException("Overlay wegschrijven mislukt: {$outputPath}");
        }
    }

    private function drawTargetFrame(GdImage $image, TargetFrame $frame, int $color): void
    {
        $centerX = (int) round($frame->centerXPixels);
        $centerY = (int) round($frame->centerYPixels);
        $diameter = (int) round($frame->outerRingRadiusPixels * 2);

        imagesetthickness($image, 2);
        imageellipse($image, $centerX, $centerY, $diameter, $diameter, $color);
        imageline($image, $centerX - 12, $centerY, $centerX + 12, $centerY, $color);
        imageline($image, $centerX, $centerY - 12, $centerX, $centerY + 12, $color);
        imagestring($image, 3, $centerX + 14, $centerY + 4, 'ring '.$frame->outerRingNumber, $color);
    }

    private function drawMarker(GdImage $image, int $x, int $y, int $radius, int $color, string $label): void
    {
        imagesetthickness($image, 3);
        imageellipse($image, $x, $y, $radius * 2, $radius * 2, $color);

        if ($label !== '') {
            imagestring($image, 3, $x + $radius + 3, $y - 7, $label, $color);
        }
    }

    /**
     * @param  list<DetectedShot>  $shots
     * @return list<string>
     */
    private function keys(array $shots): array
    {
        return array_map(fn (DetectedShot $shot): string => $this->key($shot), $shots);
    }

    private function key(DetectedShot $shot): string
    {
        return sprintf('%.4f:%.4f:%d', $shot->xNormalized, $shot->yNormalized, $shot->ring);
    }
}

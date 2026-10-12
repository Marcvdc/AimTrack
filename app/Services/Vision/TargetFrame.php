<?php

namespace App\Services\Vision;

/**
 * Waar het model de roos in de foto ziet.
 *
 * Het anker is bewust de BUITENSTE GEDRUKTE RING en niet ring 1. Niet elke roos
 * drukt ring 1 af: er zitten kaarten in de meetset waarvan de buitenste ring een 6
 * is. Vroeg je zo'n kaart om te normaliseren op "de rand van ring 1", dan koos het
 * model noodgedwongen de buitenste ring die het wel zag, en dan klopte de schaal
 * niet meer. Door het ringnummer erbij te vragen is de schaal altijd eenduidig.
 */
final readonly class TargetFrame
{
    public function __construct(
        public float $centerXPixels,
        public float $centerYPixels,
        public float $outerRingRadiusPixels,
        public int $outerRingNumber,
    ) {}

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function fromArray(?array $payload): ?self
    {
        if ($payload === null) {
            return null;
        }

        $radius = (float) ($payload['outer_ring_radius_px'] ?? 0.0);

        if ($radius <= 0.0) {
            return null;
        }

        return new self(
            centerXPixels: (float) ($payload['center_x_px'] ?? 0.0),
            centerYPixels: (float) ($payload['center_y_px'] ?? 0.0),
            outerRingRadiusPixels: $radius,
            outerRingNumber: max(1, min(10, (int) ($payload['outer_ring_number'] ?? 1))),
        );
    }

    /**
     * Reken een genormaliseerde schotpositie terug naar pixels in de verkleinde foto.
     *
     * @return array{x: float, y: float}
     */
    public function toPixels(float $xNormalized, float $yNormalized): array
    {
        return [
            'x' => $this->centerXPixels + $xNormalized * $this->outerRingRadiusPixels,
            'y' => $this->centerYPixels + $yNormalized * $this->outerRingRadiusPixels,
        ];
    }

    /**
     * De factor die een positie, genormaliseerd op de buitenste gedrukte ring,
     * omrekent naar de ring1-schaal die het schotbord gebruikt.
     *
     * Bij gelijke ringstappen ligt de buitenrand van ring N op (11 - N) / 10 van de
     * ring1-straal. Is de buitenste ring een 1, dan is de factor 1.0 en verandert er
     * niets; is het een 6, dan is hij 0.5.
     */
    public function ring1Scale(): float
    {
        return (11 - $this->outerRingNumber) / 10.0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'center_x_px' => round($this->centerXPixels, 1),
            'center_y_px' => round($this->centerYPixels, 1),
            'outer_ring_radius_px' => round($this->outerRingRadiusPixels, 1),
            'outer_ring_number' => $this->outerRingNumber,
            'ring1_scale' => round($this->ring1Scale(), 3),
        ];
    }
}

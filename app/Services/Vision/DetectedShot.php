<?php

namespace App\Services\Vision;

/**
 * Eén vers kogelgat zoals het model het aanwijst.
 *
 * De positie is genormaliseerd ten opzichte van het roos-centrum: (0,0) is het
 * midden en straal 1.0 is de buitenrand van de BUITENSTE GEDRUKTE RING. Welke ring
 * dat is, staat in TargetFrame; niet elke roos drukt ring 1 af.
 */
final readonly class DetectedShot
{
    public function __construct(
        public float $xNormalized,
        public float $yNormalized,
        public int $ring,
        public float $confidence,
        public string $kind,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            xNormalized: (float) ($payload['x_norm'] ?? 0.0),
            yNormalized: (float) ($payload['y_norm'] ?? 0.0),
            ring: max(0, min(10, (int) ($payload['ring'] ?? 0))),
            confidence: max(0.0, min(1.0, (float) ($payload['confidence'] ?? 0.0))),
            kind: is_string($payload['kind'] ?? null) ? $payload['kind'] : 'hole',
        );
    }

    /**
     * De afstand tot het midden, in ring1-stralen.
     */
    public function distanceNormalized(): float
    {
        return hypot($this->xNormalized, $this->yNormalized);
    }

    /**
     * De positie in de 0..1-conventie van het schotbord, waarbij (0.5, 0.5) het
     * midden is en straal 0.5 de rand van ring 1. Dit is dezelfde conventie als
     * die van handmatig geplaatste schoten in session_shots.
     *
     * ``$ring1Scale`` rekent de positie, die genormaliseerd is op de buitenste
     * gedrukte ring, om naar de ring1-schaal van het bord. Bij een roos die ring 1
     * wel afdrukt is die factor 1.0; bij een kaart waarvan de buitenste ring een 6
     * is, 0.5. Zonder die factor zouden schoten van zo'n kaart twee keer te ver van
     * het midden op het bord belanden.
     *
     * @return array{x: float, y: float}
     */
    public function toBoardCoordinates(float $ring1Scale = 1.0): array
    {
        return [
            'x' => 0.5 + $this->xNormalized * $ring1Scale * 0.5,
            'y' => 0.5 + $this->yNormalized * $ring1Scale * 0.5,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'x_norm' => round($this->xNormalized, 4),
            'y_norm' => round($this->yNormalized, 4),
            'ring' => $this->ring,
            'confidence' => round($this->confidence, 3),
            'kind' => $this->kind,
            'distance_norm' => round($this->distanceNormalized(), 4),
        ];
    }
}

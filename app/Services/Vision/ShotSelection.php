<?php

namespace App\Services\Vision;

/**
 * Wat de zeef overhield, en wat hij waarom heeft laten vallen.
 *
 * De twee redenen staan apart omdat ze iets heel verschillends betekenen. Een schot
 * dat op zekerheid afvalt, kan een echt gat zijn dat de drempel wegnam; dat is een
 * meetfout in onze afstelling. Een schot dat afvalt omdat het aantal al vol was,
 * is een overtelling; dat is een eigenschap van het model. Die twee op een hoop
 * gooien maakt het onmogelijk om de drempel te ijken.
 */
final readonly class ShotSelection
{
    /**
     * @param  list<DetectedShot>  $kept
     * @param  list<DetectedShot>  $droppedLowConfidence
     * @param  list<DetectedShot>  $droppedOverCount
     */
    public function __construct(
        public array $kept,
        public array $droppedLowConfidence,
        public array $droppedOverCount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kept' => count($this->kept),
            'dropped_low_confidence' => count($this->droppedLowConfidence),
            'dropped_over_count' => count($this->droppedOverCount),
            'low_confidence_values' => array_map(
                static fn (DetectedShot $shot): float => round($shot->confidence, 3),
                $this->droppedLowConfidence,
            ),
        ];
    }
}

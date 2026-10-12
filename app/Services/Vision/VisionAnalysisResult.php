<?php

namespace App\Services\Vision;

use App\Enums\TargetType;

/**
 * De volledige uitkomst van één analyse van één roosfoto.
 */
final readonly class VisionAnalysisResult
{
    /**
     * @param  list<DetectedShot>  $shots
     * @param  list<RejectedCandidate>  $rejected
     */
    public function __construct(
        public TargetType $targetType,
        public array $shots,
        public array $rejected,
        public ?TargetFrame $frame,
        public string $orientationNote,
        public float $overallConfidence,
        public bool $countMatchesExpected,
        public ?int $expectedShotCount,
        public string $model,
        public int $imageWidth,
        public int $imageHeight,
        public TokenUsage $usage = new TokenUsage,
    ) {}

    public function detectedCount(): int
    {
        return count($this->shots);
    }

    /**
     * De opgetelde ringwaarde, wat in elke discipline in scope gelijk is aan de score.
     */
    public function totalScore(): int
    {
        return array_sum(array_map(static fn (DetectedShot $shot): int => $shot->ring, $this->shots));
    }

    /**
     * @return list<int>
     */
    public function rings(): array
    {
        return array_map(static fn (DetectedShot $shot): int => $shot->ring, $this->shots);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'target_type' => $this->targetType->value,
            'model' => $this->model,
            'usage' => $this->usage->toArray(),
            'image' => ['width' => $this->imageWidth, 'height' => $this->imageHeight],
            'frame' => $this->frame?->toArray(),
            'expected_shot_count' => $this->expectedShotCount,
            'detected_count' => $this->detectedCount(),
            'count_matches_expected' => $this->countMatchesExpected,
            'overall_confidence' => round($this->overallConfidence, 3),
            'orientation_note' => $this->orientationNote,
            'total_score' => $this->totalScore(),
            'shots' => array_map(static fn (DetectedShot $shot): array => $shot->toArray(), $this->shots),
            'rejected' => array_map(static fn (RejectedCandidate $item): array => $item->toArray(), $this->rejected),
        ];
    }
}

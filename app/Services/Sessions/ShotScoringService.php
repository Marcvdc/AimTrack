<?php

namespace App\Services\Sessions;

use Illuminate\Support\Collection;

class ShotScoringService
{
    public const MAX_SCORE = 10;

    /**
     * Bepaal ring en score o.b.v. genormaliseerde coordinaten (0..1) waarbij (0.5,0.5) het middelpunt is.
     */
    public function scoreShot(float $xNormalized, float $yNormalized, array $options = []): array
    {
        $centerOffsetX = $xNormalized - 0.5;
        $centerOffsetY = $yNormalized - 0.5;
        $distance = sqrt($centerOffsetX ** 2 + $centerOffsetY ** 2);
        $maxRadius = $options['max_radius'] ?? 0.5;

        $normalizedDistance = min($distance / $maxRadius, 1);
        $rawScore = (1 - $normalizedDistance) * self::MAX_SCORE;
        $ring = (int) ceil($rawScore);
        $ring = max(0, min(self::MAX_SCORE, $ring));

        return [
            'distance_from_center' => $distance,
            'ring' => $ring,
            'score' => $ring,
        ];
    }

    /**
     * Decimaal-score (één decimaal) afgeleid uit de afstand tot het midden.
     * Puur weergave: dezelfde lineaire schaal als scoreShot(), maar zonder de
     * ceil()-afronding naar hele ringen. Wijzigt de opgeslagen score niet.
     */
    public function decimalScore(float $distanceFromCenter, array $options = []): float
    {
        $maxRadius = $options['max_radius'] ?? 0.5;
        $normalizedDistance = min(max($distanceFromCenter, 0) / $maxRadius, 1);
        $rawScore = (1 - $normalizedDistance) * self::MAX_SCORE;

        return round(max(0, $rawScore), 1);
    }

    /**
     * Bereken aggregaties per sessie.
     */
    public function aggregate(Collection $shots): array
    {
        $total = $shots->sum('score');
        $turns = $shots->groupBy('turn_index')
            ->map(fn ($group) => [
                'shots' => $group->count(),
                'score' => $group->sum('score'),
                'average' => $group->avg('score'),
            ])
            ->map(fn ($data) => [
                ...$data,
                'average' => round($data['average'], 2),
            ])
            ->toArray();

        return [
            'total_score' => $total,
            'shot_count' => $shots->count(),
            'average_score' => $shots->avg('score'),
            'turns' => $turns,
        ];
    }
}

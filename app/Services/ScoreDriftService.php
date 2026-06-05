<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SessionShot;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Score-drift: de gemiddelde score per schot-positie over de laatste N sessies
 * van een schutter. Maakt zichtbaar wáár in een sessie de score wegzakt
 * (bv. de typische concentratiedip rond schot 30–40 uit het ontwerp).
 */
final class ScoreDriftService
{
    public function __construct(private readonly User $user) {}

    /**
     * Gemiddelde score per 1-based schot-positie over de laatste $sessions
     * sessies (alleen sessies met schoten). Leeg bij onvoldoende data.
     *
     * @return array<int, float>
     */
    public function perShotAverage(int $sessions = 6): array
    {
        $sessionIds = $this->user->sessions()
            ->whereHas('shots')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit($sessions)
            ->pluck('id');

        if ($sessionIds->isEmpty()) {
            return [];
        }

        $byPosition = SessionShot::query()
            ->whereIn('session_id', $sessionIds)
            ->orderBy('session_id')
            ->orderBy('turn_index')
            ->orderBy('shot_index')
            ->orderBy('id')
            ->get(['session_id', 'score'])
            ->groupBy('session_id')
            ->reduce(function (array $carry, Collection $shots): array {
                foreach ($shots->values() as $position => $shot) {
                    $carry[$position + 1][] = (int) $shot->score;
                }

                return $carry;
            }, []);

        ksort($byPosition);

        return array_map(
            static fn (array $scores): float => round(array_sum($scores) / count($scores), 2),
            $byPosition,
        );
    }

    /**
     * Zwakste aaneengesloten venster van $window schoten (laagste gemiddelde).
     * Returnt null bij minder posities dan $window.
     *
     * @return array{from: int, to: int, average: float}|null
     */
    public function weakestWindow(int $window = 10, int $sessions = 6): ?array
    {
        $averages = $this->perShotAverage($sessions);
        $positions = array_keys($averages);

        if (count($positions) < $window) {
            return null;
        }

        $values = array_values($averages);
        $bestStart = 0;
        $bestSum = null;

        for ($i = 0; $i + $window <= count($values); $i++) {
            $sum = array_sum(array_slice($values, $i, $window));

            if ($bestSum === null || $sum < $bestSum) {
                $bestSum = $sum;
                $bestStart = $i;
            }
        }

        return [
            'from' => $positions[$bestStart],
            'to' => $positions[$bestStart + $window - 1],
            'average' => round($bestSum / $window, 2),
        ];
    }
}

<?php

namespace App\Services\Sessions;

use App\Models\Session;
use App\Models\SessionShot;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SessionShotService
{
    public function __construct(private readonly ShotScoringService $scoringService) {}

    public function recordShot(Session $session, int $turnIndex, float $xNormalized, float $yNormalized, array $metadata = []): SessionShot
    {
        $x = $this->clamp($xNormalized);
        $y = $this->clamp($yNormalized);

        return DB::transaction(function () use ($session, $turnIndex, $x, $y, $metadata) {
            $shotIndex = ($session->shots()
                ->where('turn_index', $turnIndex)
                ->max('shot_index') ?? -1) + 1;

            $scoreData = $this->scoringService->scoreShot($x, $y);

            return $session->shots()->create([
                'turn_index' => $turnIndex,
                'shot_index' => $shotIndex,
                'x_normalized' => $x,
                'y_normalized' => $y,
                'distance_from_center' => $scoreData['distance_from_center'],
                'ring' => $scoreData['ring'],
                'score' => $scoreData['score'],
                'metadata' => Arr::only($metadata, ['input_device', 'notes']),
            ]);
        });
    }

    public function deleteShot(SessionShot $shot): void
    {
        $shot->delete();
    }

    public function summarize(Session $session): array
    {
        $shots = $session->shots()->orderBy('turn_index')->orderBy('shot_index')->get();

        return $this->scoringService->aggregate($shots);
    }

    private function clamp(float $value): float
    {
        if (! is_finite($value)) {
            throw new InvalidArgumentException('Coordinate must be finite.');
        }

        return max(0, min(1, $value));
    }
}

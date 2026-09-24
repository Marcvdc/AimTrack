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

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_PHOTO = 'photo';

    public const SOURCE_PHOTO_CORRECTED = 'photo_corrected';

    public function recordShot(
        Session $session,
        int $turnIndex,
        float $xNormalized,
        float $yNormalized,
        array $metadata = [],
        string $source = self::SOURCE_MANUAL,
    ): SessionShot {
        $x = $this->clamp($xNormalized);
        $y = $this->clamp($yNormalized);

        return DB::transaction(function () use ($session, $turnIndex, $x, $y, $metadata, $source) {
            $shotIndex = ($session->shots()
                ->where('turn_index', $turnIndex)
                ->max('shot_index') ?? -1) + 1;

            $scoreData = $this->scoringService->scoreShot($x, $y);

            return $session->shots()->create([
                'turn_index' => $turnIndex,
                'shot_index' => $shotIndex,
                'source' => $source,
                'x_normalized' => $x,
                'y_normalized' => $y,
                'distance_from_center' => $scoreData['distance_from_center'],
                'ring' => $scoreData['ring'],
                'score' => $scoreData['score'],
                'metadata' => Arr::only($metadata, ['input_device', 'notes', 'confidence', 'read_ring', 'photo_path']),
            ]);
        });
    }

    /**
     * Wist de schoten die eerder uit een foto van deze beurt zijn gekomen.
     *
     * Een beurt opnieuw analyseren moet de vorige uitkomst vervangen en niet
     * verdubbelen. Handmatig geplaatste en met de hand gecorrigeerde schoten blijven
     * staan: die zijn door een mens neergezet en mag een job niet weggooien.
     */
    public function clearPhotoShots(Session $session, int $turnIndex): int
    {
        return $session->shots()
            ->where('turn_index', $turnIndex)
            ->where('source', self::SOURCE_PHOTO)
            ->delete();
    }

    /**
     * Verplaats een schot naar een nieuwe plek op het bord en herbereken de score.
     *
     * Kwam het schot uit een foto, dan wordt de bron `photo_corrected` en bewaren we
     * de oorspronkelijke positie in de metadata. Dat is niet alleen administratie:
     * het verschil tussen wat het model zei en wat de schutter ervan maakte, is
     * precies de grondwaarheid waar de detectie op geijkt kan worden. Zo vult de
     * meetset zich tijdens gebruik in plaats van in een aparte labelsessie.
     */
    public function moveShot(SessionShot $shot, float $xNormalized, float $yNormalized): SessionShot
    {
        $x = $this->clamp($xNormalized);
        $y = $this->clamp($yNormalized);
        $scoreData = $this->scoringService->scoreShot($x, $y);

        $metadata = $shot->metadata ?? [];

        if ($shot->source === self::SOURCE_PHOTO) {
            $metadata['corrected_from'] = [
                'x' => (float) $shot->x_normalized,
                'y' => (float) $shot->y_normalized,
                'ring' => $shot->ring,
            ];
        }

        $shot->update([
            'x_normalized' => $x,
            'y_normalized' => $y,
            'distance_from_center' => $scoreData['distance_from_center'],
            'ring' => $scoreData['ring'],
            'score' => $scoreData['score'],
            'source' => $shot->source === self::SOURCE_PHOTO
                ? self::SOURCE_PHOTO_CORRECTED
                : $shot->source,
            'metadata' => $metadata,
        ]);

        return $shot->refresh();
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

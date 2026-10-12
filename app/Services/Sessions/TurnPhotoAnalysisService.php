<?php

namespace App\Services\Sessions;

use App\Enums\TargetType;
use App\Models\Session;
use App\Models\SessionTurnAnalysis;
use App\Services\Ai\AiKeyResolver;
use App\Services\Vision\DetectedShot;
use App\Services\Vision\ShotSelection;
use App\Services\Vision\ShotSelector;
use App\Services\Vision\TargetPhotoAnalyzer;
use App\Services\Vision\VisionAnalysisResult;
use App\Services\Vision\VisionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Zet een foto van een beurt om in schoten op het schotbord.
 *
 * Twee uitgangspunten die het gedrag bepalen.
 *
 * De foto gaat nooit verloren. Ook als de herkenning faalt blijft het pad bewaard
 * en staat de beurt op 'te controleren', zodat een schutter zijn beurt handmatig
 * kan afmaken in plaats van zijn foto kwijt te zijn.
 *
 * Er wordt nooit aangevuld tot het verwachte aantal. Te weinig schoten met een
 * controlevlag is beter dan een plakker die als schot op het bord komt.
 */
class TurnPhotoAnalysisService
{
    public function __construct(
        private readonly TargetPhotoAnalyzer $analyzer,
        private readonly ShotSelector $selector,
        private readonly SessionShotService $shots,
        private readonly AiKeyResolver $keys,
    ) {}

    public function analyze(
        Session $session,
        int $turnIndex,
        string $photoPath,
        ?int $expectedShotCount = null,
        ?string $disk = null,
    ): SessionTurnAnalysis {
        $disk ??= config('filesystems.default');
        $targetType = $session->target_type ?? TargetType::KKP_25M;

        try {
            $result = $this->analyzer->analyze(
                imagePath: Storage::disk($disk)->path($photoPath),
                targetType: $targetType,
                expectedShotCount: $expectedShotCount,
                apiKey: $this->keys->forUser($session->user),
            );
        } catch (VisionException $exception) {
            return $this->recordFailure($session, $turnIndex, $photoPath, $expectedShotCount, $exception->getMessage());
        }

        $selection = $this->selector->select($result->shots, $expectedShotCount);

        return DB::transaction(function () use ($session, $turnIndex, $photoPath, $expectedShotCount, $result, $selection) {
            $this->shots->clearPhotoShots($session, $turnIndex);
            $this->persistShots($session, $turnIndex, $photoPath, $result, $selection);

            return $this->recordSuccess($session, $turnIndex, $photoPath, $expectedShotCount, $result, $selection);
        });
    }

    private function persistShots(
        Session $session,
        int $turnIndex,
        string $photoPath,
        VisionAnalysisResult $result,
        ShotSelection $selection,
    ): void {
        $scale = $result->frame?->ring1Scale() ?? 1.0;

        foreach ($selection->kept as $shot) {
            $board = $shot->toBoardCoordinates($scale);

            $this->shots->recordShot(
                session: $session,
                turnIndex: $turnIndex,
                xNormalized: $board['x'],
                yNormalized: $board['y'],
                metadata: [
                    'confidence' => round($shot->confidence, 3),
                    /*
                     * De ring die het model van de gedrukte ringen aflas. Die kan
                     * afwijken van de ring die uit de positie volgt, en dan is dat
                     * hier terug te zien in plaats van stilzwijgend overschreven.
                     */
                    'read_ring' => $shot->ring,
                    'photo_path' => $photoPath,
                ],
                source: SessionShotService::SOURCE_PHOTO,
            );
        }
    }

    private function recordSuccess(
        Session $session,
        int $turnIndex,
        string $photoPath,
        ?int $expectedShotCount,
        VisionAnalysisResult $result,
        ShotSelection $selection,
    ): SessionTurnAnalysis {
        $detected = count($selection->kept);
        $countMatches = $expectedShotCount === null || $detected === $expectedShotCount;

        return $this->store($session, $turnIndex, [
            'status' => SessionTurnAnalysis::STATUS_DONE,
            /*
             * Elke beurt uit een foto wordt gemarkeerd om te controleren. De detectie
             * is goed maar niet feilloos, en de correctie is twee klikken werk; een
             * fout die ongemerkt in de statistiek belandt kost veel meer.
             */
            'needs_review' => true,
            'review_reason' => $this->reviewReason($detected, $expectedShotCount, $countMatches, $selection),
            'expected_shot_count' => $expectedShotCount,
            'detected_count' => $detected,
            'dropped_low_confidence' => count($selection->droppedLowConfidence),
            'rejected_by_model' => count($result->rejected),
            'overall_confidence' => round($result->overallConfidence, 3),
            'photo_path' => $photoPath,
            'model' => $result->model,
            'metadata' => [
                'frame' => $result->frame?->toArray(),
                'orientation_note' => $result->orientationNote,
                'rings' => array_map(static fn (DetectedShot $shot): int => $shot->ring, $selection->kept),
                'usage' => $result->usage->toArray(),
            ],
        ]);
    }

    private function recordFailure(
        Session $session,
        int $turnIndex,
        string $photoPath,
        ?int $expectedShotCount,
        string $reason,
    ): SessionTurnAnalysis {
        return $this->store($session, $turnIndex, [
            'status' => SessionTurnAnalysis::STATUS_FAILED,
            'needs_review' => true,
            'review_reason' => 'De foto kon niet worden herkend: '.$reason.' Je foto is bewaard; '
                .'zet de schoten zo nodig handmatig op het bord.',
            'expected_shot_count' => $expectedShotCount,
            'detected_count' => null,
            'photo_path' => $photoPath,
        ]);
    }

    private function reviewReason(
        int $detected,
        ?int $expected,
        bool $countMatches,
        ShotSelection $selection,
    ): string {
        $redenen = [];

        if (! $countMatches) {
            $redenen[] = sprintf(
                'Er zijn %d schoten herkend terwijl je er %d hebt ingevuld.',
                $detected,
                (int) $expected,
            );
        }

        if ($selection->droppedLowConfidence !== []) {
            $redenen[] = sprintf(
                '%d mogelijke treffer(s) waren te onzeker en zijn niet geplaatst.',
                count($selection->droppedLowConfidence),
            );
        }

        if ($selection->droppedOffTarget !== []) {
            $redenen[] = sprintf(
                '%d gat(en) lagen buiten de kaart en zijn niet geplaatst.',
                count($selection->droppedOffTarget),
            );
        }

        $redenen[] = 'Controleer de markers en bevestig de beurt.';

        return implode(' ', $redenen);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function store(Session $session, int $turnIndex, array $attributes): SessionTurnAnalysis
    {
        return SessionTurnAnalysis::updateOrCreate(
            ['session_id' => $session->id, 'turn_index' => $turnIndex],
            $attributes,
        );
    }
}

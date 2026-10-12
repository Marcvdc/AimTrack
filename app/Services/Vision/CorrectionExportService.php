<?php

namespace App\Services\Vision;

use App\Models\SessionShot;
use App\Models\SessionTurnAnalysis;
use Illuminate\Support\Facades\Storage;

/**
 * Bouwt een meetset-manifest uit de beurten die een mens heeft bevestigd.
 *
 * Dit sluit de cirkel. Het labelen van foto's is tot nu toe twee keer misgegaan:
 * de eerste set van 49 foto's is verdwenen, en de labels die overbleven waren door
 * het model zelf ingevuld. Door de bevestigde beurten te exporteren komt de
 * grondwaarheid uit het gewone gebruik: wie een marker versleept of weggooit,
 * levert daarmee een gelabelde foto op die blijft staan.
 *
 * Alleen bevestigde beurten tellen mee. Een beurt die nog op controleren staat, is
 * per definitie geen grondwaarheid.
 */
class CorrectionExportService
{
    /**
     * @return array<string, mixed>
     */
    public function build(string $disk = 'local'): array
    {
        $analyses = SessionTurnAnalysis::query()
            ->where('needs_review', false)
            ->whereNotNull('photo_path')
            ->with('session.shots')
            ->orderBy('session_id')
            ->orderBy('turn_index')
            ->get();

        $photos = [];
        $overgeslagen = [];

        foreach ($analyses as $analysis) {
            if (! Storage::disk($disk)->exists($analysis->photo_path)) {
                $overgeslagen[] = [
                    'photo_path' => $analysis->photo_path,
                    'reden' => 'foto niet meer aanwezig op de schijf',
                ];

                continue;
            }

            $shots = $analysis->session->shots
                ->where('turn_index', $analysis->turn_index)
                ->sortBy('shot_index');

            $photos[] = [
                'file' => Storage::disk($disk)->path($analysis->photo_path),
                'target_type' => $analysis->session->target_type?->value ?? 'kkp_25m',
                'expected_shot_count' => $shots->count(),
                'truth_rings' => $shots->map(fn (SessionShot $shot): int => (int) $shot->ring)->values()->all(),
                'bron' => [
                    'session_id' => $analysis->session_id,
                    'turn_index' => $analysis->turn_index,
                    'gecorrigeerd' => $shots->where('source', 'photo_corrected')->count(),
                    'handmatig_toegevoegd' => $shots->where('source', 'manual')->count(),
                ],
            ];
        }

        return [
            '_herkomst' => 'Samengesteld uit bevestigde beurten in AimTrack. De ringen komen uit de '
                .'schoten zoals ze na correctie op het bord staan, dus dit is menselijke grondwaarheid '
                .'en geen modeluitvoer.',
            'photos_dir' => '/',
            'photos' => $photos,
            'overgeslagen' => $overgeslagen,
        ];
    }
}

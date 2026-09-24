<?php

namespace App\Services\Vision;

/**
 * Zeeft de ruwe modeluitvoer tot de schoten die we daadwerkelijk als schot tellen.
 *
 * Twee regels, in deze volgorde:
 * 1. Alles onder de zekerheidsdrempel valt af.
 * 2. Houden er dan nog meer schoten over dan de schutter zegt gelost te hebben,
 *    dan vallen de minst zekere af.
 *
 * Wat er NIET gebeurt: aanvullen tot het verwachte aantal. Te weinig schoten met
 * een controlevlag is beter dan een plakker die als schot op het bord komt.
 *
 * De drempel staat laag (0.25). Bij 0.4 bleek hij midden in de verdeling te liggen,
 * want de mediane zekerheid over de meetset was 0.45, en op de overlays waren de
 * weggegooide markers zichtbaar echte gaten. Wat afvalt wordt apart geteld, zodat
 * een te scherpe drempel zichtbaar is in plaats van stilletjes schoten te slikken.
 */
class ShotSelector
{
    public function __construct(
        private readonly float $minConfidence = 0.25,
    ) {}

    /**
     * @param  list<DetectedShot>  $shots
     */
    public function select(array $shots, ?int $expectedShotCount): ShotSelection
    {
        $kept = [];
        $lowConfidence = [];

        foreach ($shots as $shot) {
            if ($shot->confidence >= $this->minConfidence) {
                $kept[] = $shot;
            } else {
                $lowConfidence[] = $shot;
            }
        }

        $overCount = [];

        if ($expectedShotCount !== null && count($kept) > $expectedShotCount) {
            usort($kept, static fn (DetectedShot $a, DetectedShot $b): int => $b->confidence <=> $a->confidence);
            $overCount = array_slice($kept, $expectedShotCount);
            $kept = array_slice($kept, 0, $expectedShotCount);
        }

        return new ShotSelection(
            kept: array_values($kept),
            droppedLowConfidence: array_values($lowConfidence),
            droppedOverCount: array_values($overCount),
        );
    }
}

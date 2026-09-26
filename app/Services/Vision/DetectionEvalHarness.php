<?php

namespace App\Services\Vision;

use App\Enums\TargetType;
use Closure;
use Throwable;

/**
 * Draait de detectie over een gelabelde set foto's en levert de cijfers waarmee
 * de architectuurkeuze te maken is.
 *
 * Per foto wordt de analyse meerdere keren onafhankelijk gedraaid. Dat is geen
 * luxe: een vision-model is niet deterministisch, en de vraag of de detectie
 * bruikbaar is, gaat net zo goed over de spreiding tussen runs als over de
 * gemiddelde uitkomst.
 */
class DetectionEvalHarness
{
    public function __construct(
        private readonly TargetPhotoAnalyzer $analyzer,
        private readonly ShotSelector $selector,
        private readonly DetectionMetrics $metrics,
        private readonly TargetOverlayRenderer $overlays,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $entries  genormaliseerde manifest-regels
     * @param  Closure(string, int, int): void|null  $progress  (bestandsnaam, run, totaal runs)
     * @param  list<array<string, mixed>>  $alreadyDone  foto's uit een eerdere, afgebroken meting
     * @param  Closure(array<string, mixed>): void|null  $onPhotoDone  krijgt het rapport na elke foto
     * @return array<string, mixed>
     */
    public function run(
        array $entries,
        string $apiKey,
        int $runs = 3,
        ?string $overlayDirectory = null,
        ?Closure $progress = null,
        array $alreadyDone = [],
        ?Closure $onPhotoDone = null,
    ): array {
        $photos = array_values($alreadyDone);
        $verwerkt = array_column($photos, 'file');

        foreach ($entries as $entry) {
            if (in_array(basename((string) $entry['path']), $verwerkt, true)) {
                continue;
            }

            $photos[] = $this->evaluatePhoto($entry, $apiKey, $runs, $overlayDirectory, $progress);
            $report = $this->assemble($photos, $runs);

            /*
             * Na elke foto wegschrijven. Een meting over tientallen foto's duurt meer
             * dan een uur; valt het proces daarna om, dan is zonder deze regel alles
             * kwijt en is het geld voor niets uitgegeven.
             */
            if ($onPhotoDone !== null) {
                $onPhotoDone($report);
            }
        }

        return $this->assemble($photos, $runs);
    }

    /**
     * @param  list<array<string, mixed>>  $photos
     * @return array<string, mixed>
     */
    private function assemble(array $photos, int $runs): array
    {
        $comparisons = [];

        foreach ($photos as $photo) {
            foreach ($photo['runs'] ?? [] as $run) {
                if (isset($run['comparison'])) {
                    $comparisons[] = $run['comparison'];
                }
            }
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'runs_per_photo' => $runs,
            'model' => config('vision.model'),
            'effort' => config('vision.effort'),
            'photos' => $photos,
            'aggregate' => $this->metrics->aggregate($comparisons),
            'per_discipline' => $this->perDiscipline($photos),
            'kosten' => $this->kosten($photos),
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  Closure(string, int, int): void|null  $progress
     * @return array<string, mixed>
     */
    private function evaluatePhoto(
        array $entry,
        string $apiKey,
        int $runs,
        ?string $overlayDirectory,
        ?Closure $progress,
    ): array {
        $path = (string) $entry['path'];
        $name = basename($path);
        $targetType = $entry['target_type'] instanceof TargetType
            ? $entry['target_type']
            : TargetType::from((string) $entry['target_type']);
        $expected = $entry['expected_shot_count'] ?? null;
        $truthRings = $entry['truth_rings'] ?? null;

        $runResults = [];
        $selectedPerRun = [];

        for ($run = 1; $run <= $runs; $run++) {
            if ($progress !== null) {
                $progress($name, $run, $runs);
            }

            try {
                $result = $this->analyzer->analyze($path, $targetType, $expected, $apiKey);
            } catch (Throwable $exception) {
                $runResults[] = ['run' => $run, 'error' => $exception->getMessage()];

                continue;
            }

            $selection = $this->selector->select($result->shots, $expected);
            $selected = $selection->kept;
            $selectedPerRun[] = $selected;

            $rings = array_map(static fn (DetectedShot $shot): int => $shot->ring, $selected);
            $outerRing = $result->frame?->outerRingNumber ?? 1;

            $runResult = [
                'run' => $run,
                'raw_detected' => $result->detectedCount(),
                'selected' => count($selected),
                'selection' => $selection->toArray(),
                'rejected_by_model' => count($result->rejected),
                'rings' => $rings,
                'total_score' => array_sum($rings),
                'overall_confidence' => round($result->overallConfidence, 3),
                'usage' => $result->usage->toArray(),
                'frame' => $result->frame?->toArray(),
                'ring_position_agreement' => $this->metrics->ringPositionAgreement($selected, $outerRing),
                'orientation_note' => $result->orientationNote,
                'analysis' => $result->toArray(),
            ];

            if (is_array($truthRings)) {
                $runResult['comparison'] = $this->metrics->compareTurn($truthRings, $rings);
            }

            if ($run === 1 && $overlayDirectory !== null) {
                $runResult['overlay'] = $this->renderOverlay($path, $result, $selected, $overlayDirectory, $name);
            }

            $runResults[] = $runResult;
        }

        return [
            'file' => $name,
            'path' => $path,
            'target_type' => $targetType->value,
            'expected_shot_count' => $expected,
            'truth_rings' => $truthRings,
            'runs' => $runResults,
            'stability' => $this->metrics->positionalStability($selectedPerRun),
        ];
    }

    /**
     * @param  list<DetectedShot>  $selected
     */
    private function renderOverlay(
        string $path,
        VisionAnalysisResult $result,
        array $selected,
        string $directory,
        string $name,
    ): string {
        $output = rtrim($directory, '/').'/'.pathinfo($name, PATHINFO_FILENAME).'-overlay.jpg';

        try {
            $this->overlays->render($path, $result, $selected, $output);
        } catch (Throwable $exception) {
            return 'mislukt: '.$exception->getMessage();
        }

        return $output;
    }

    /**
     * Wat de meting tot nu toe aan tokens en geld heeft gekost.
     *
     * Dit hoort in het rapport omdat een geschatte prijs vooraf niet betrouwbaar is:
     * het denkwerk van het model zit in de output-tokens en loopt sterk uiteen per
     * foto. Achteraf meten is de enige manier om te weten wat een ronde kost.
     *
     * @param  list<array<string, mixed>>  $photos
     * @return array<string, mixed>
     */
    private function kosten(array $photos): array
    {
        $totaal = new TokenUsage;
        $calls = 0;

        foreach ($photos as $photo) {
            foreach ($photo['runs'] ?? [] as $run) {
                if (isset($run['error']) || ! is_array($run['usage'] ?? null)) {
                    continue;
                }

                $totaal = $totaal->plus(new TokenUsage(
                    inputTokens: (int) $run['usage']['input_tokens'],
                    outputTokens: (int) $run['usage']['output_tokens'],
                    cacheReadTokens: (int) $run['usage']['cache_read_tokens'],
                    cacheCreationTokens: (int) $run['usage']['cache_creation_tokens'],
                ));
                $calls++;
            }
        }

        $dollars = $totaal->estimatedCost(
            (float) config('vision.price_input_per_million'),
            (float) config('vision.price_output_per_million'),
        );

        return [
            'calls' => $calls,
            'tokens' => $totaal->toArray(),
            'geschatte_kosten_usd' => round($dollars, 2),
            'per_call_usd' => $calls === 0 ? null : round($dollars / $calls, 3),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $photos
     * @return array<string, mixed>
     */
    private function perDiscipline(array $photos): array
    {
        $grouped = [];

        foreach ($photos as $photo) {
            foreach ($photo['runs'] as $run) {
                if (isset($run['comparison'])) {
                    $grouped[$photo['target_type']][] = $run['comparison'];
                }
            }
        }

        return array_map(
            fn (array $comparisons): array => $this->metrics->aggregate($comparisons),
            $grouped,
        );
    }
}

<?php

namespace App\Services\Vision;

/**
 * Schrijft de meetuitkomst weg als JSON (volledig, om op door te rekenen) en als
 * Markdown (om naar te kijken en in de issue te plakken).
 */
class DetectionReportWriter
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function writeJson(array $report, string $path): void
    {
        $this->ensureDirectory($path);
        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function writeMarkdown(array $report, string $path): void
    {
        $this->ensureDirectory($path);
        file_put_contents($path, $this->toMarkdown($report));
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function toMarkdown(array $report): string
    {
        $lines = [
            '# Detectiemeting foto naar schoten',
            '',
            sprintf('Model: `%s` (effort `%s`)', $report['model'] ?? 'onbekend', $report['effort'] ?? 'onbekend'),
            sprintf('Runs per foto: %d', $report['runs_per_photo'] ?? 0),
            sprintf('Gedraaid op: %s', $report['generated_at'] ?? ''),
            '',
            '## Kopcijfers',
            '',
        ];

        $lines = array_merge($lines, $this->kostenLines($report['kosten'] ?? []), ['']);

        $lines = array_merge($lines, $this->aggregateLines(
            $report['aggregate'] ?? [],
            $this->hasGroundTruth($report['photos'] ?? []),
        ));

        $perDiscipline = $report['per_discipline'] ?? [];

        if (is_array($perDiscipline) && $perDiscipline !== []) {
            $lines[] = '';
            $lines[] = '## Per discipline';
            $lines[] = '';
            $lines[] = '| Discipline | Beurten | Aantal goed | Ring goed | Ring-MAE | Overteld | Onderteld |';
            $lines[] = '|---|---|---|---|---|---|---|';

            foreach ($perDiscipline as $discipline => $aggregate) {
                $lines[] = sprintf(
                    '| `%s` | %d | %s | %s | %s | %d | %d |',
                    $discipline,
                    $aggregate['turns'] ?? 0,
                    $this->percentage($aggregate['count_accuracy'] ?? null),
                    $this->percentage($aggregate['mean_ring_accuracy'] ?? null),
                    $this->number($aggregate['mean_ring_mae'] ?? null),
                    $aggregate['over_count_turns'] ?? 0,
                    $aggregate['under_count_turns'] ?? 0,
                );
            }
        }

        $lines[] = '';
        $lines[] = '## Ring tegen positie';
        $lines[] = '';
        $lines[] = 'Leest het model de ring anders dan de positie suggereert, dan klopt de ring wel';
        $lines[] = 'maar de genormaliseerde plek niet, en dan is de positie onbruikbaar om de marker';
        $lines[] = 'op het schotbord te zetten zonder correctie.';
        $lines[] = '';
        $lines = array_merge($lines, $this->agreementLines($report['photos'] ?? []));

        $lines[] = '';
        $lines[] = '## Per foto';
        $lines[] = '';
        $lines[] = '| Foto | Discipline | Verwacht | Gevonden per run | Onder drempel | Afgewezen | Buitenste ring | Spreiding |';
        $lines[] = '|---|---|---|---|---|---|---|---|';

        foreach ($report['photos'] ?? [] as $photo) {
            $counts = [];
            $laag = [];
            $rejected = [];
            $buitenste = [];

            foreach ($photo['runs'] ?? [] as $run) {
                if (isset($run['error'])) {
                    $counts[] = 'fout';

                    continue;
                }

                $counts[] = (string) ($run['selected'] ?? 0);
                $laag[] = (string) ($run['selection']['dropped_low_confidence'] ?? 0);
                $rejected[] = (string) ($run['rejected_by_model'] ?? 0);
                $buitenste[] = (string) ($run['frame']['outer_ring_number'] ?? '?');
            }

            $lines[] = sprintf(
                '| %s | `%s` | %s | %s | %s | %s | %s | %s |',
                $photo['file'] ?? '',
                $photo['target_type'] ?? '',
                $photo['expected_shot_count'] ?? 'onbekend',
                implode(', ', $counts),
                implode(', ', $laag),
                implode(', ', $rejected),
                implode(', ', array_unique($buitenste)),
                $this->spread($photo['stability'] ?? []),
            );
        }

        $errors = $this->errorSection($report['photos'] ?? []);

        if ($errors !== '') {
            $lines = array_merge($lines, ['', $errors]);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<string, mixed>  $aggregate
     * @return list<string>
     */
    private function aggregateLines(array $aggregate, bool $hasGroundTruth): array
    {
        if (($aggregate['turns'] ?? 0) === 0) {
            return [
                $hasGroundTruth
                    ? 'Er is grondwaarheid, maar geen enkele run is geslaagd, dus er valt niets te vergelijken. Zie de mislukte runs hieronder.'
                    : 'Geen vergelijkbare beurten. Zonder grondwaarheid in het manifest meet dit alleen spreiding.',
            ];
        }

        return [
            sprintf('- Beurten gemeten: **%d**', $aggregate['turns']),
            sprintf('- Aantal schoten exact goed: **%s**', $this->percentage($aggregate['count_accuracy'] ?? null)),
            sprintf('- Ringwaarde exact goed: **%s**', $this->percentage($aggregate['mean_ring_accuracy'] ?? null)),
            sprintf('- Gemiddelde ringafwijking (MAE): **%s**', $this->number($aggregate['mean_ring_mae'] ?? null)),
            sprintf('- Beurten met overtelling: **%d**', $aggregate['over_count_turns'] ?? 0),
            sprintf('- Beurten met ondertelling: **%d**', $aggregate['under_count_turns'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $kosten
     * @return list<string>
     */
    private function kostenLines(array $kosten): array
    {
        if (($kosten['calls'] ?? 0) === 0) {
            return ['_Geen geslaagde calls, dus geen kosten gemeten._'];
        }

        $tokens = $kosten['tokens'];

        return [
            sprintf(
                '_%d call(s), %s input- en %s output-tokens, geschat **$%s** (ongeveer $%s per foto)._',
                $kosten['calls'],
                number_format($tokens['input_tokens'] + $tokens['cache_creation_tokens'] + $tokens['cache_read_tokens'], 0, ',', '.'),
                number_format($tokens['output_tokens'], 0, ',', '.'),
                number_format((float) $kosten['geschatte_kosten_usd'], 2, ',', '.'),
                number_format((float) ($kosten['per_call_usd'] ?? 0), 3, ',', '.'),
            ),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $photos
     * @return list<string>
     */
    private function agreementLines(array $photos): array
    {
        $shots = 0;
        $agree = 0;
        $deltas = [];

        foreach ($photos as $photo) {
            foreach ($photo['runs'] ?? [] as $run) {
                $agreement = $run['ring_position_agreement'] ?? null;

                if (! is_array($agreement) || ($agreement['shots'] ?? 0) === 0) {
                    continue;
                }

                $shots += $agreement['shots'];
                $agree += $agreement['agree'];
                $deltas[] = $agreement['mean_abs_delta'] * $agreement['shots'];
            }
        }

        if ($shots === 0) {
            return ['Geen schoten gedetecteerd, dus niets te vergelijken.'];
        }

        return [
            sprintf('- Schoten beoordeeld: **%d**', $shots),
            sprintf('- Gelezen ring gelijk aan de ring uit de afstand: **%s**', $this->percentage($agree / $shots)),
            sprintf('- Gemiddelde afwijking: **%s ring**', $this->number(array_sum($deltas) / $shots)),
        ];
    }

    /**
     * Is er voor minstens een foto vastgelegd wat er echt geschoten is? Zonder dat
     * meet de harness alleen spreiding, en dat is iets anders dan een meting die
     * wel kon maar waarvan elke run mislukte.
     *
     * @param  list<array<string, mixed>>  $photos
     */
    private function hasGroundTruth(array $photos): bool
    {
        foreach ($photos as $photo) {
            if (($photo['truth_rings'] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $photos
     */
    private function errorSection(array $photos): string
    {
        $errors = [];

        foreach ($photos as $photo) {
            foreach ($photo['runs'] ?? [] as $run) {
                if (isset($run['error'])) {
                    $errors[] = sprintf('- %s (run %d): %s', $photo['file'] ?? '', $run['run'] ?? 0, $run['error']);
                }
            }
        }

        if ($errors === []) {
            return '';
        }

        return implode("\n", array_merge(['## Mislukte runs', ''], $errors));
    }

    /**
     * @param  array<string, mixed>  $stability
     */
    private function spread(array $stability): string
    {
        if (($stability['mean_spread'] ?? null) === null) {
            return 'n.v.t.';
        }

        return sprintf(
            '%s gem. / %s max',
            $this->percentage($stability['mean_spread']),
            $this->percentage($stability['max_spread'] ?? null),
        );
    }

    private function percentage(?float $value): string
    {
        return $value === null ? 'n.v.t.' : round($value * 100, 1).'%';
    }

    private function number(?float $value): string
    {
        return $value === null ? 'n.v.t.' : (string) round($value, 2);
    }

    private function ensureDirectory(string $path): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0o775, true);
        }
    }
}

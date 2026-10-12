<?php

namespace App\Services\Vision;

/**
 * De meetlat voor de detectie. Poort van app/validation/metrics.py uit de
 * Python-service, zodat een eerder gedraaide meting vergelijkbaar blijft.
 *
 * Schoten worden gekoppeld op aflopend gesorteerde ringwaarde. Dat is grof, want
 * de grondwaarheid kent geen posities, maar het is stabiel en het meet precies de
 * twee dingen die ertoe doen: klopt het aantal, en kloppen de ringen.
 */
class DetectionMetrics
{
    /**
     * Vergelijk de echte ringen van een beurt met de voorspelde ringen.
     *
     * @param  list<int>  $truthRings
     * @param  list<int>  $predictedRings
     * @return array{count_correct: bool, count_delta: int, ring_accuracy: float, ring_mae: float|null}
     */
    public function compareTurn(array $truthRings, array $predictedRings): array
    {
        $countDelta = count($predictedRings) - count($truthRings);
        $countCorrect = $countDelta === 0;

        if ($truthRings === []) {
            return [
                'count_correct' => $countCorrect,
                'count_delta' => $countDelta,
                'ring_accuracy' => $predictedRings === [] ? 1.0 : 0.0,
                'ring_mae' => $predictedRings === [] ? 0.0 : null,
            ];
        }

        if ($predictedRings === []) {
            return [
                'count_correct' => false,
                'count_delta' => $countDelta,
                'ring_accuracy' => 0.0,
                'ring_mae' => null,
            ];
        }

        rsort($truthRings);
        rsort($predictedRings);

        $pairs = min(count($truthRings), count($predictedRings));
        $hits = 0;
        $absoluteError = 0;

        for ($i = 0; $i < $pairs; $i++) {
            if ($truthRings[$i] === $predictedRings[$i]) {
                $hits++;
            }

            $absoluteError += abs($truthRings[$i] - $predictedRings[$i]);
        }

        return [
            'count_correct' => $countCorrect,
            'count_delta' => $countDelta,
            'ring_accuracy' => (float) ($hits / $pairs),
            'ring_mae' => (float) ($absoluteError / $pairs),
        ];
    }

    /**
     * Rol de per-beurt-uitkomsten op tot de kopcijfers.
     *
     * @param  list<array{count_correct: bool, count_delta: int, ring_accuracy: float, ring_mae: float|null}>  $results
     * @return array<string, mixed>
     */
    public function aggregate(array $results): array
    {
        $total = count($results);

        if ($total === 0) {
            return ['turns' => 0];
        }

        $maes = array_values(array_filter(
            array_column($results, 'ring_mae'),
            static fn (?float $mae): bool => $mae !== null,
        ));

        return [
            'turns' => $total,
            'count_accuracy' => (float) (count(array_filter(array_column($results, 'count_correct'))) / $total),
            'mean_ring_accuracy' => (float) (array_sum(array_column($results, 'ring_accuracy')) / $total),
            'mean_ring_mae' => $maes === [] ? null : (float) (array_sum($maes) / count($maes)),
            'over_count_turns' => count(array_filter($results, static fn (array $r): bool => $r['count_delta'] > 0)),
            'under_count_turns' => count(array_filter($results, static fn (array $r): bool => $r['count_delta'] < 0)),
        ];
    }

    /**
     * Klopt de genormaliseerde positie met de ring die het model afleest?
     *
     * Het model levert twee dingen die onafhankelijk van elkaar fout kunnen zijn:
     * de RING, direct van de gedrukte ringen afgelezen, en de POSITIE, genormaliseerd
     * op een ring1-straal die het model zelf schat. Schat het die straal te groot,
     * dan blijft de ring goed maar kruipen de markers naar het midden. Voor de score
     * maakt dat niets uit, voor de plek op het schotbord alles.
     *
     * Deze controle rekent de ring terug uit de afstand, uitgaande van gelijke
     * ringstappen (klopt voor de 25m-rozen en Woerden; bij de 50m-geweerroos wijkt
     * ring 10 af, dus daar is een afwijking van 1 ring geen bewijs van een fout).
     *
     * ``$outerRing`` is het nummer van de buitenste gedrukte ring. Zonder die
     * parameter rekende de controle elke roos af alsof ring 1 erop stond, en dan
     * lijkt een kaart die bij ring 6 ophoudt er meters naast te zitten terwijl het
     * model gewoon gelijk heeft.
     *
     * @param  list<DetectedShot>  $shots
     * @return array{shots: int, agree: int, mean_abs_delta: float|null, max_abs_delta: int|null}
     */
    public function ringPositionAgreement(array $shots, int $outerRing = 1): array
    {
        if ($shots === []) {
            return ['shots' => 0, 'agree' => 0, 'mean_abs_delta' => null, 'max_abs_delta' => null];
        }

        $agree = 0;
        $deltas = [];

        foreach ($shots as $shot) {
            $delta = abs($shot->ring - $this->ringFromDistance($shot->distanceNormalized(), $outerRing));
            $deltas[] = $delta;

            if ($delta === 0) {
                $agree++;
            }
        }

        return [
            'shots' => count($shots),
            'agree' => $agree,
            'mean_abs_delta' => (float) (array_sum($deltas) / count($deltas)),
            'max_abs_delta' => max($deltas),
        ];
    }

    /**
     * De ring die bij een genormaliseerde afstand hoort bij gelijke ringstappen.
     *
     * De afstand is genormaliseerd op de buitenrand van de buitenste gedrukte ring
     * N, die dus op 1.0 ligt. Er passen (11 - N) ringen tussen het midden en die
     * rand, dus ring k eindigt op (11 - k) / (11 - N). Voor een volledige roos
     * (N = 1) valt dit terug op de vertrouwde 1.0 voor ring 1 en 0.1 voor ring 10.
     */
    public function ringFromDistance(float $distanceNormalized, int $outerRing = 1): int
    {
        if ($distanceNormalized > 1.0) {
            return 0;
        }

        $ringen = 11 - max(1, min(10, $outerRing));

        return max(0, min(10, 11 - (int) ceil($distanceNormalized * $ringen)));
    }

    /**
     * Hoe stabiel legt het model dezelfde beurt over meerdere runs neer.
     *
     * Elk schot uit run 1 wordt gekoppeld aan het dichtstbijzijnde schot uit een
     * volgende run; de gemiddelde en maximale koppelafstand zijn de spreiding,
     * uitgedrukt in ring1-stralen. Dat is dezelfde maat als de meting van 18 juli,
     * die op 4 tot 9 procent uitkwam.
     *
     * @param  list<list<DetectedShot>>  $runs
     * @return array{runs: int, mean_spread: float|null, max_spread: float|null, count_spread: int}
     */
    public function positionalStability(array $runs): array
    {
        $runs = array_values(array_filter($runs, static fn (array $run): bool => $run !== []));
        $counts = array_map('count', $runs);

        $stability = [
            'runs' => count($runs),
            'mean_spread' => null,
            'max_spread' => null,
            'count_spread' => $counts === [] ? 0 : max($counts) - min($counts),
        ];

        if (count($runs) < 2) {
            return $stability;
        }

        $reference = $runs[0];
        $distances = [];

        foreach (array_slice($runs, 1) as $run) {
            foreach ($reference as $shot) {
                $closest = null;

                foreach ($run as $candidate) {
                    $distance = hypot(
                        $shot->xNormalized - $candidate->xNormalized,
                        $shot->yNormalized - $candidate->yNormalized,
                    );

                    if ($closest === null || $distance < $closest) {
                        $closest = $distance;
                    }
                }

                if ($closest !== null) {
                    $distances[] = $closest;
                }
            }
        }

        if ($distances === []) {
            return $stability;
        }

        $stability['mean_spread'] = array_sum($distances) / count($distances);
        $stability['max_spread'] = max($distances);

        return $stability;
    }
}

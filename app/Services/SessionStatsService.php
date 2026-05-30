<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Session;
use Illuminate\Support\Collection;

/**
 * Berekent afgeleide statistieken voor één sessie op basis van haar shots.
 *
 * Lazy: shots worden één keer geladen en gecachet binnen de instance.
 */
final class SessionStatsService
{
    private ?Collection $cachedShots = null;

    public function __construct(private readonly Session $session) {}

    /**
     * Score-array (0–10) in chronologische volgorde.
     *
     * @return array<int, int>
     */
    public function scores(): array
    {
        return $this->shots()
            ->pluck('score')
            ->map(fn ($s): int => (int) $s)
            ->all();
    }

    public function totalShots(): int
    {
        return $this->shots()->count();
    }

    public function totalScore(): int
    {
        return (int) $this->shots()->sum('score');
    }

    public function tienen(): int
    {
        return $this->shots()->where('ring', 10)->count();
    }

    public function negens(): int
    {
        return $this->shots()->where('ring', 9)->count();
    }

    public function bestShot(): ?int
    {
        $max = $this->shots()->max('score');

        return $max !== null ? (int) $max : null;
    }

    /**
     * Geschatte groep-diameter in mm. Gebaseerd op 2 × σ van de
     * normalised hit-coords, geschaald naar een ISSF 10m luchtpistool
     * target diameter (155.5mm). Returnt null bij <2 shots.
     */
    public function groupMm(): ?float
    {
        $shots = $this->shots();

        if ($shots->count() < 2) {
            return null;
        }

        $xs = $shots->pluck('x_normalized')->map(fn ($v): float => (float) $v)->all();
        $ys = $shots->pluck('y_normalized')->map(fn ($v): float => (float) $v)->all();

        $varX = $this->variance($xs);
        $varY = $this->variance($ys);

        $sigma = sqrt($varX + $varY);
        $targetDiameterMm = 155.5;

        return round(2.0 * $sigma * $targetDiameterMm, 1);
    }

    /**
     * Gemiddelde cadans (seconden tussen opeenvolgende schoten).
     * Returnt null als shots geen onderlinge timestamp-verschillen tonen.
     *
     * Schoten met exact gelijke timestamps (delta 0) worden bewust
     * overgeslagen: dat duidt op bulk-inserts (bv. seeding of import),
     * geen echte cadans. Een sessie waarvan álle deltas 0 zijn levert null.
     */
    public function avgCadansSec(): ?float
    {
        $shots = $this->shots()->values();

        if ($shots->count() < 2) {
            return null;
        }

        $deltas = [];
        for ($i = 1; $i < $shots->count(); $i++) {
            $prev = $shots[$i - 1]->created_at;
            $curr = $shots[$i]->created_at;

            if ($prev === null || $curr === null) {
                continue;
            }

            // abs(): Carbon 3 levert een signed diff op; we willen de
            // magnitude van de tijd tussen twee schoten.
            $delta = abs($curr->diffInSeconds($prev));

            if ($delta > 0) {
                $deltas[] = $delta;
            }
        }

        if ($deltas === []) {
            return null;
        }

        return round(array_sum($deltas) / count($deltas), 1);
    }

    /**
     * Series-scores: groepeert shots per $perSerie en sommeert per groep.
     * Series is empty bij geen shots.
     *
     * @return array<int, int>
     */
    public function seriesScores(int $perSerie = 10): array
    {
        $scores = $this->scores();

        if ($scores === [] || $perSerie < 1) {
            return [];
        }

        return collect($scores)
            ->chunk($perSerie)
            ->filter(fn (Collection $chunk): bool => $chunk->count() === $perSerie)
            ->map(fn (Collection $chunk): int => (int) $chunk->sum())
            ->values()
            ->all();
    }

    /**
     * Schoten-per-serie afleiden uit total_shots. ≤10 → totaal als 1 serie,
     * anders 10 (ISSF-default). Werkt met decision 2 uit het PLAN.
     */
    public function shotsPerSerie(): int
    {
        $total = $this->totalShots();

        if ($total === 0) {
            return 10;
        }

        return $total < 10 ? $total : 10;
    }

    /**
     * Indices (0-based, inclusief) van de zwakste serie.
     * Bv. bij 6 series van 10 schoten en de 4e serie laagst → [30, 39].
     * Returnt null bij <2 volle series.
     *
     * @return array{0: int, 1: int}|null
     */
    public function dipRange(): ?array
    {
        $perSerie = $this->shotsPerSerie();
        $series = $this->seriesScores($perSerie);

        if (count($series) < 2) {
            return null;
        }

        // Geen echte dip als alle series even hoog scoren.
        if (min($series) === max($series)) {
            return null;
        }

        $minIndex = 0;
        foreach ($series as $i => $sum) {
            if ($sum < $series[$minIndex]) {
                $minIndex = $i;
            }
        }

        return [$minIndex * $perSerie, ($minIndex + 1) * $perSerie - 1];
    }

    private function shots(): Collection
    {
        if ($this->cachedShots === null) {
            $this->cachedShots = $this->session
                ->shots()
                ->orderBy('turn_index')
                ->orderBy('shot_index')
                ->orderBy('id')
                ->get();
        }

        return $this->cachedShots;
    }

    /**
     * @param  array<int, float>  $values
     */
    private function variance(array $values): float
    {
        $n = count($values);

        if ($n === 0) {
            return 0.0;
        }

        $mean = array_sum($values) / $n;
        $sumSquaredDiff = 0.0;

        foreach ($values as $v) {
            $sumSquaredDiff += ($v - $mean) ** 2;
        }

        return $sumSquaredDiff / $n;
    }
}

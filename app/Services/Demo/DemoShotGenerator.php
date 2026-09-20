<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Enums\Deviation;
use App\Services\Sessions\ShotScoringService;

/**
 * Genereert de schoten achter de demo-data.
 *
 * De demo-knop beloofde een gevulde app maar leverde geen enkel SessionShot,
 * waardoor de roos, de ringen en de eindscore leeg bleven: precies de
 * kernfeature die een nieuwe gebruiker wil zien. Deze klasse vult dat gat.
 *
 * Het patroon volgt de verhaallijn die al in de demo-sessies staat: een
 * wapenregel met Deviation::LEFT levert een groep die daadwerkelijk naar
 * links trekt, zodat de roos laat zien wat de reflectietekst beweert.
 *
 * De reeks is deterministisch: dezelfde seed geeft dezelfde schoten. Dat
 * houdt de demo voor iedere gebruiker gelijk en laat tests er hard op
 * asserten. Bewust géén mt_srand(): dat zet de globale random-state van het
 * proces om en beïnvloedt alles wat er daarna met Faker gebeurt.
 */
final class DemoShotGenerator
{
    /** Schoten per serie; volgt de ISSF-default die SessionStatsService ook aanhoudt. */
    public const SHOTS_PER_TURN = 10;

    /** Spreiding (1 sigma) van de groep in genormaliseerde coordinaten. */
    private const SPREAD = 0.045;

    /** Hoe ver het middelpunt van de groep opschuift bij een afwijking. */
    private const BIAS = 0.055;

    public function __construct(private readonly ShotScoringService $scoring) {}

    /**
     * @param  int  $rounds  Aantal schoten; komt overeen met rounds_fired van de wapenregel.
     * @param  int  $seed  Vaste seed, zodat dezelfde wapenregel altijd dezelfde groep geeft.
     * @param  int  $turnOffset  Eerste turn_index; laat opeenvolgende wapenregels doortellen binnen één sessie.
     * @return list<array{turn_index: int, shot_index: int, x_normalized: float, y_normalized: float, distance_from_center: float, ring: int, score: int}>
     */
    public function generate(int $rounds, Deviation $deviation, int $seed, int $turnOffset = 0): array
    {
        if ($rounds < 1) {
            return [];
        }

        [$biasX, $biasY] = $this->bias($deviation);

        $state = $this->normalizeSeed($seed);
        $shots = [];

        for ($i = 0; $i < $rounds; $i++) {
            $x = $this->clamp(0.5 + $biasX + $this->gaussian($state) * self::SPREAD);
            $y = $this->clamp(0.5 + $biasY + $this->gaussian($state) * self::SPREAD);

            $scored = $this->scoring->scoreShot($x, $y);

            $shots[] = [
                'turn_index' => $turnOffset + intdiv($i, self::SHOTS_PER_TURN),
                'shot_index' => $i % self::SHOTS_PER_TURN,
                'x_normalized' => round($x, 5),
                'y_normalized' => round($y, 5),
                'distance_from_center' => round((float) $scored['distance_from_center'], 5),
                'ring' => (int) $scored['ring'],
                'score' => (int) $scored['score'],
            ];
        }

        return $shots;
    }

    /**
     * Aantal series dat $rounds schoten oplevert, zodat de aanroeper weet
     * waar de turn_index van de volgende wapenregel moet beginnen.
     */
    public function turnsFor(int $rounds): int
    {
        return $rounds < 1 ? 0 : (int) ceil($rounds / self::SHOTS_PER_TURN);
    }

    /**
     * y = 0 is de bovenkant van de roos (zie de marker-positionering in
     * session-shot-board.blade.php), dus "hoog" betekent een kleinere y.
     *
     * @return array{0: float, 1: float}
     */
    private function bias(Deviation $deviation): array
    {
        return match ($deviation) {
            Deviation::LEFT => [-self::BIAS, 0.0],
            Deviation::RIGHT => [self::BIAS, 0.0],
            Deviation::HIGH => [0.0, -self::BIAS],
            Deviation::LOW => [0.0, self::BIAS],
            Deviation::NONE => [0.0, 0.0],
        };
    }

    /**
     * Lineaire congruentiegenerator (dezelfde parameters als glibc), puur
     * om een reproduceerbare stroom te hebben zonder de globale
     * random-state van PHP aan te raken.
     */
    private function next(int &$state): float
    {
        $state = ($state * 1103515245 + 12345) % 2147483648;

        return $state / 2147483648;
    }

    /**
     * Normaalverdeling via Box-Muller: een echte schotgroep is rond en
     * dicht bij het midden, niet gelijkmatig over het hele vlak verdeeld.
     */
    private function gaussian(int &$state): float
    {
        $u1 = max($this->next($state), 1.0e-9);
        $u2 = $this->next($state);

        return sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);
    }

    private function normalizeSeed(int $seed): int
    {
        return abs($seed) % 2147483648;
    }

    /**
     * Binnen de roos houden; scoreShot() rekent met 0..1 waarbij (0.5, 0.5)
     * het middelpunt is.
     */
    private function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }
}

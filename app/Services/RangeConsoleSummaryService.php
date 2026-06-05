<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiReflection;
use App\Models\Session;
use App\Models\SessionShot;
use App\Models\User;
use App\Models\Weapon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Aggregator voor de Range Console sessies-overzicht pagina.
 *
 * Bundelt KPI's, "last session" + "latest reflection" + trend-data
 * voor één user. Read-only, één instance per request.
 */
final class RangeConsoleSummaryService
{
    public function __construct(private readonly User $user) {}

    public function sessionsThisMonth(): int
    {
        return $this->userSessions()
            ->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }

    public function sessionsThisMonthDelta(): int
    {
        return $this->sessionsThisMonth() - $this->sessionsLastMonth();
    }

    private function sessionsLastMonth(): int
    {
        return $this->userSessions()
            ->whereBetween('date', [
                now()->subMonthNoOverflow()->startOfMonth(),
                now()->subMonthNoOverflow()->endOfMonth(),
            ])
            ->count();
    }

    public function totalSessions(): int
    {
        return $this->userSessions()->count();
    }

    /**
     * Aantal schoten over de laatste 30 dagen (count, niet score-som).
     */
    public function shotsLast30d(): int
    {
        return SessionShot::query()
            ->whereIn('session_id', $this->userSessions()
                ->whereDate('date', '>=', now()->subDays(30)->startOfDay())
                ->select('id'))
            ->count();
    }

    /**
     * Beste 10-shot serie over alle sessies van de user. Returnt null
     * als er geen volle serie van 10 is gelogd.
     *
     * Eén query voor alle shots (geen N+1 over sessies): groepeer per
     * sessie, hak in series van 10 en pak de hoogste volledige serie.
     */
    public function bestSeriesScore(): ?int
    {
        $sessionIds = $this->userSessions()->pluck('id');

        if ($sessionIds->isEmpty()) {
            return null;
        }

        $shotsBySession = SessionShot::query()
            ->whereIn('session_id', $sessionIds)
            ->orderBy('session_id')
            ->orderBy('turn_index')
            ->orderBy('shot_index')
            ->orderBy('id')
            ->get(['session_id', 'score'])
            ->groupBy('session_id');

        $best = null;
        foreach ($shotsBySession as $shots) {
            foreach ($shots->pluck('score')->chunk(10) as $chunk) {
                if ($chunk->count() < 10) {
                    continue;
                }

                $serieSum = (int) $chunk->sum();

                if ($best === null || $serieSum > $best) {
                    $best = $serieSum;
                }
            }
        }

        return $best;
    }

    public function aiReflectionCount(): int
    {
        return AiReflection::query()
            ->whereIn('session_id', $this->userSessions()->select('id'))
            ->count();
    }

    /**
     * Sessies van de user zonder AI-reflectie (laatste 30 dagen),
     * een proxy voor "wachtrij".
     */
    public function pendingAiReflections(): int
    {
        return $this->userSessions()
            ->whereDate('date', '>=', now()->subDays(30)->startOfDay())
            ->whereDoesntHave('aiReflection')
            ->count();
    }

    public function lastSession(): ?Session
    {
        return $this->userSessions()
            ->with(['shots', 'sessionWeapons.weapon', 'aiReflection'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();
    }

    public function latestReflection(): ?AiReflection
    {
        return AiReflection::query()
            ->whereIn('session_id', $this->userSessions()->select('id'))
            ->latest('updated_at')
            ->first();
    }

    /**
     * Som van shot-scores per sessie over de laatste $days dagen.
     * Sleutel = ISO datum (één sessie per dag, anders combineerd).
     *
     * @return array<string, int>
     */
    public function trend30d(int $days = 30): array
    {
        $since = now()->subDays($days)->startOfDay();

        $sessionIds = $this->userSessions()
            ->whereDate('date', '>=', $since)
            ->pluck('id');

        if ($sessionIds->isEmpty()) {
            return [];
        }

        $scoresBySession = SessionShot::query()
            ->whereIn('session_id', $sessionIds)
            ->selectRaw('session_id, SUM(score) as total')
            ->groupBy('session_id')
            ->pluck('total', 'session_id');

        return Session::query()
            ->whereIn('id', $sessionIds)
            ->orderBy('date')
            ->get(['id', 'date'])
            ->mapWithKeys(fn (Session $s): array => [
                $s->date->toDateString() => (int) ($scoresBySession[$s->id] ?? 0),
            ])
            ->all();
    }

    /**
     * Per-wapen gebruik voor de "Wapens · gebruik" kaart: type, kaliber, naam,
     * gemiddelde sessiescore, aantal sessies + schoten, en de score-reeks voor
     * de inline sparkline. Eén aggregaat-query voor alle schoten (geen N+1).
     *
     * @return Collection<int, array{name: string, type: string, caliber: string, avg: float, sessions: int, shots: int, trend: int, series: array<int, int>}>
     */
    public function weaponUsage(int $limit = 3): Collection
    {
        $weapons = Weapon::query()
            ->where('user_id', $this->user->getKey())
            ->whereHas('sessionWeapons')
            ->withCount('sessionWeapons as usage_count')
            ->orderByDesc('usage_count')
            ->limit($limit)
            ->with(['sessionWeapons:id,weapon_id,session_id'])
            ->get();

        if ($weapons->isEmpty()) {
            return collect();
        }

        $sessionIds = $weapons
            ->flatMap(fn (Weapon $weapon): Collection => $weapon->sessionWeapons->pluck('session_id'))
            ->unique();

        $sessionStats = SessionShot::query()
            ->whereIn('session_id', $sessionIds)
            ->selectRaw('session_id, SUM(score) as total, COUNT(*) as shots')
            ->groupBy('session_id')
            ->get()
            ->keyBy('session_id');

        $sessionDates = Session::query()
            ->whereIn('id', $sessionIds)
            ->pluck('date', 'id');

        return $weapons->map(function (Weapon $weapon) use ($sessionStats, $sessionDates): array {
            $orderedSessionIds = $weapon->sessionWeapons
                ->pluck('session_id')
                ->unique()
                ->sortBy(fn ($id): string => (string) ($sessionDates[$id] ?? ''))
                ->values();

            $scores = $orderedSessionIds
                ->map(fn ($id): int => (int) ($sessionStats[$id]->total ?? 0))
                ->all();
            $shots = (int) $orderedSessionIds
                ->sum(fn ($id): int => (int) ($sessionStats[$id]->shots ?? 0));

            $nonZeroScores = array_values(array_filter($scores, static fn (int $score): bool => $score > 0));
            $avg = $nonZeroScores !== [] ? round(array_sum($nonZeroScores) / count($nonZeroScores), 1) : 0.0;
            $trend = count($scores) >= 2 ? ($scores[count($scores) - 1] - $scores[0]) : 0;

            return [
                'name' => $weapon->name,
                'type' => $weapon->weapon_type?->value ? ucfirst($weapon->weapon_type->value) : '—',
                'caliber' => (string) ($weapon->caliber ?? ''),
                'avg' => $avg,
                'sessions' => $orderedSessionIds->count(),
                'shots' => $shots,
                'trend' => $trend,
                'series' => $scores,
            ];
        });
    }

    /**
     * Voortgang per discipline (leergebied) = wapentype + afstand. Per discipline:
     * aantal sessies, totaal schoten, gem. sessiescore, een chronologische
     * score-reeks (voor de sparkline) en de trend (laatste − eerste).
     *
     * @return Collection<int, array{label: string, sessions: int, shots: int, avg: float, trend: int, series: array<int, int>}>
     */
    public function disciplineProgress(int $limit = 6): Collection
    {
        $sessions = $this->userSessions()
            ->with(['sessionWeapons.weapon:id,weapon_type'])
            ->orderBy('date')
            ->orderBy('id')
            ->get(['id', 'date']);

        if ($sessions->isEmpty()) {
            return collect();
        }

        $stats = SessionShot::query()
            ->whereIn('session_id', $sessions->pluck('id'))
            ->selectRaw('session_id, SUM(score) as total, COUNT(*) as shots')
            ->groupBy('session_id')
            ->get()
            ->keyBy('session_id');

        $grouped = [];

        foreach ($sessions as $session) {
            $entry = $session->sessionWeapons->first();
            $type = $entry?->weapon?->weapon_type?->value;

            if ($type === null) {
                continue;
            }

            $distance = $entry->distance_m;
            $label = ucfirst($type).($distance ? " {$distance}m" : '');
            $stat = $stats->get($session->id);

            $grouped[$label] ??= ['sessions' => 0, 'shots' => 0, 'series' => []];
            $grouped[$label]['sessions']++;
            $grouped[$label]['shots'] += (int) ($stat->shots ?? 0);
            $grouped[$label]['series'][] = (int) ($stat->total ?? 0);
        }

        return collect($grouped)
            ->map(function (array $data, string $label): array {
                $nonZero = array_values(array_filter($data['series'], static fn (int $score): bool => $score > 0));
                $avg = $nonZero !== [] ? round(array_sum($nonZero) / count($nonZero), 1) : 0.0;
                $series = $data['series'];
                $trend = count($series) >= 2 ? ($series[count($series) - 1] - $series[0]) : 0;

                return [
                    'label' => $label,
                    'sessions' => $data['sessions'],
                    'shots' => $data['shots'],
                    'avg' => $avg,
                    'trend' => $trend,
                    'series' => $series,
                ];
            })
            ->sortByDesc('sessions')
            ->values()
            ->take($limit);
    }

    private function userSessions(): Builder
    {
        return Session::query()->where('user_id', $this->user->getKey());
    }
}

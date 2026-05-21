<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Session;
use App\Models\SessionWeapon;
use App\Models\Weapon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Levert KPI's, trend-data en recente sessies voor één wapen.
 *
 * Gebruikt door WeaponResource view-pagina en list-pagina-widgets.
 */
final class WeaponInsightsService
{
    public function __construct(private readonly Weapon $weapon) {}

    public function sessionCount(): int
    {
        return $this->weapon->sessionWeapons()->distinct('session_id')->count('session_id');
    }

    public function totalShots(): int
    {
        return (int) $this->weapon->sessionWeapons()->sum('rounds_fired');
    }

    /**
     * Gemiddelde score (som per sessie) over alle sessies met dit wapen.
     */
    public function avgScore(): ?float
    {
        $perSession = $this->scoresPerSession();

        if ($perSession->isEmpty()) {
            return null;
        }

        return round($perSession->avg(), 1);
    }

    public function bestScore(): ?int
    {
        $perSession = $this->scoresPerSession();

        if ($perSession->isEmpty()) {
            return null;
        }

        return (int) $perSession->max();
    }

    public function bestScoreDate(): ?Carbon
    {
        $perSession = $this->scoresPerSession();

        if ($perSession->isEmpty()) {
            return null;
        }

        $maxScore = $perSession->max();
        $sessionId = $perSession->search($maxScore);

        if ($sessionId === false) {
            return null;
        }

        $session = Session::query()->find($sessionId);

        return $session?->date;
    }

    /**
     * Score-trend over de laatste $days dagen, per sessie.
     * Sleutel = ISO datum, waarde = sessie-totaalscore.
     *
     * @return array<string, int>
     */
    public function trendData(int $days = 30): array
    {
        $since = now()->subDays($days)->startOfDay();

        $sessionIds = $this->sessionsWithWeaponQuery()
            ->whereDate('date', '>=', $since)
            ->pluck('id');

        if ($sessionIds->isEmpty()) {
            return [];
        }

        $shots = \App\Models\SessionShot::query()
            ->whereIn('session_id', $sessionIds)
            ->selectRaw('session_id, SUM(score) as total')
            ->groupBy('session_id')
            ->pluck('total', 'session_id');

        return Session::query()
            ->whereIn('id', $sessionIds)
            ->orderBy('date')
            ->get(['id', 'date'])
            ->mapWithKeys(fn (Session $s): array => [
                $s->date->toDateString() => (int) ($shots[$s->id] ?? 0),
            ])
            ->all();
    }

    /**
     * Laatste $limit sessies waarin dit wapen is gebruikt.
     */
    public function recentSessions(int $limit = 5): Collection
    {
        return $this->sessionsWithWeaponQuery()
            ->orderByDesc('date')
            ->limit($limit)
            ->get();
    }

    /**
     * Map: session_id => totaalscore (som van shots) voor sessies met dit wapen.
     */
    private function scoresPerSession(): Collection
    {
        $sessionIds = $this->sessionsWithWeaponQuery()->pluck('id');

        if ($sessionIds->isEmpty()) {
            return collect();
        }

        return \App\Models\SessionShot::query()
            ->whereIn('session_id', $sessionIds)
            ->selectRaw('session_id, SUM(score) as total')
            ->groupBy('session_id')
            ->pluck('total', 'session_id')
            ->map(fn ($v): int => (int) $v);
    }

    private function sessionsWithWeaponQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Session::query()
            ->whereIn('id', SessionWeapon::query()
                ->where('weapon_id', $this->weapon->getKey())
                ->select('session_id'));
    }
}

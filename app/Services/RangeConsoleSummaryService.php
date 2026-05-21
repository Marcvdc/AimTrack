<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiReflection;
use App\Models\Session;
use App\Models\SessionShot;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

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

    public function sessionsLastMonth(): int
    {
        return $this->userSessions()
            ->whereBetween('date', [
                now()->subMonthNoOverflow()->startOfMonth(),
                now()->subMonthNoOverflow()->endOfMonth(),
            ])
            ->count();
    }

    public function sessionsThisMonthDelta(): int
    {
        return $this->sessionsThisMonth() - $this->sessionsLastMonth();
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
     */
    public function bestSeriesScore(): ?int
    {
        $sessions = $this->userSessions()->get();

        $best = null;
        foreach ($sessions as $session) {
            foreach ((new SessionStatsService($session))->seriesScores(10) as $serieSum) {
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

    private function userSessions(): Builder
    {
        return Session::query()->where('user_id', $this->user->getKey());
    }
}

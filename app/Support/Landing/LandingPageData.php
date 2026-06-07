<?php

namespace App\Support\Landing;

use App\Models\AiReflection;
use App\Models\Session;
use App\Models\SessionWeapon;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;

class LandingPageData
{
    public function __construct(private readonly CacheRepository $cache) {}

    /**
     * Instance-brede aggregaten voor de publieke landingspagina.
     *
     * Bewust geen per-gebruiker data: dit is de gezamenlijke activiteit van de
     * (self-hosted) instance. Gebaseerd op wat altijd gevuld is bij een sessie
     * (sessies, afgevuurde schoten via SessionWeapon, AI-reflecties) i.p.v. de
     * optionele per-schot ring-data. Gecachet — het draait op een gast-pagina.
     *
     * @return array{
     *     has_data: bool,
     *     total_sessions: int,
     *     total_rounds: int,
     *     ai_reflections: int
     * }
     */
    public function stats(): array
    {
        $ttl = (int) config('landing.stats_cache_ttl', 3600);

        return $this->cache->remember('landing.stats', $ttl, function (): array {
            $totalSessions = Session::query()->count();

            return [
                'has_data' => $totalSessions > 0,
                'total_sessions' => $totalSessions,
                'total_rounds' => (int) SessionWeapon::query()->sum('rounds_fired'),
                'ai_reflections' => AiReflection::query()->count(),
            ];
        });
    }

    /**
     * Tijdreeks van afgevuurde schoten per sessie-datum (chronologisch), voor de
     * Trends-sparkline in de feature-grid.
     *
     * Bewust op `SessionWeapon.rounds_fired` per `Session.date` i.p.v. de
     * per-schot ring-data (die tabel is leeg bij summary-sessies). Lege instance
     * → lege reeks; de sparkline-component tekent dan een nette nullijn.
     *
     * @return list<int>
     */
    public function trendSeries(int $points = 12): array
    {
        $ttl = (int) config('landing.stats_cache_ttl', 3600);

        return $this->cache->remember('landing.trend', $ttl, function () use ($points): array {
            return Session::query()
                ->withSum('sessionWeapons as rounds_total', 'rounds_fired')
                ->orderBy('date')
                ->get()
                ->groupBy(fn (Session $session): string => $session->date->toDateString())
                ->map(fn (Collection $group): int => (int) $group->sum('rounds_total'))
                ->values()
                ->slice(-$points)
                ->values()
                ->all();
        });
    }

    /**
     * Meest recente sessies (instance-breed) voor de Sessies-feature-kaart.
     *
     * @return list<array{date: ?string, range: string, rounds: int}>
     */
    public function recentSessions(int $limit = 3): array
    {
        $ttl = (int) config('landing.stats_cache_ttl', 3600);

        return $this->cache->remember('landing.recent_sessions', $ttl, function () use ($limit): array {
            return Session::query()
                ->withSum('sessionWeapons as rounds_total', 'rounds_fired')
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(fn (Session $session): array => [
                    'date' => $session->date?->format('d-m'),
                    'range' => $session->range_name ?: ($session->location ?: '—'),
                    'rounds' => (int) $session->rounds_total,
                ])
                ->all();
        });
    }

    /**
     * Primaire clubnaam voor de trust-strip + footer (per-instance configureerbaar).
     */
    public function club(): string
    {
        return (string) config('landing.club', 'SSV Scherpschutters');
    }

    /**
     * Optionele extra clubnamen naast de primaire club.
     *
     * @return list<string>
     */
    public function partnerClubs(): array
    {
        return array_values(array_filter((array) config('landing.partner_clubs', [])));
    }
}

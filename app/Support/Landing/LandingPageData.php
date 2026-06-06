<?php

namespace App\Support\Landing;

use App\Models\AiReflection;
use App\Models\Session;
use App\Models\SessionWeapon;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

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

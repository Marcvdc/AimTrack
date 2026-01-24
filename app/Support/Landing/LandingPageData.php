<?php

namespace App\Support\Landing;

use App\Models\AiReflection;
use App\Models\Session;
use App\Models\SessionShot;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class LandingPageData
{
    public function __construct(private readonly CacheRepository $cache) {}

    public function stats(): array
    {
        $ttl = (int) config('landing.stats_cache_ttl', 3600);

        return $this->cache->remember('landing.stats', $ttl, function (): array {
            $now = CarbonImmutable::now();
            $monthStart = $now->startOfMonth();
            $last30Days = $now->subDays(30);

            $sessionsThisMonth = Session::query()
                ->whereBetween('date', [$monthStart, $now])
                ->count();

            $recentShotsQuery = SessionShot::query()
                ->where('created_at', '>=', $last30Days);

            $averageScore = (float) (clone $recentShotsQuery)->avg('score');

            $aiReflections = AiReflection::query()
                ->where('created_at', '>=', $last30Days)
                ->count();

            $ringBuckets = (clone $recentShotsQuery)
                ->selectRaw('ring, count(*) as total')
                ->groupBy('ring')
                ->pluck('total', 'ring')
                ->toArray();

            $maxBucket = max($ringBuckets ?: [0]) ?: 1;

            $ringHeatmap = collect(range(0, 10))->map(function (int $ring) use ($ringBuckets, $maxBucket) {
                $count = (int) ($ringBuckets[$ring] ?? 0);

                return [
                    'ring' => $ring,
                    'total' => $count,
                    'intensity' => round($count / $maxBucket, 2),
                ];
            })->values()->all();

            return [
                'sessions_this_month' => $sessionsThisMonth,
                'average_score' => round($averageScore, 1),
                'ai_reflections_last_30_days' => $aiReflections,
                'ring_heatmap' => $ringHeatmap,
            ];
        });
    }

    public function knsaLinks(): array
    {
        return config('landing.knsa_links', []);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Publiek bereikbaar via /health en /api/health, dus het antwoord bevat per check
 * alleen de status en bij een fout een vaste foutcode. Welke queue-connection,
 * queue of disk er draait, blijft binnen de applicatie (zie #119 en #123).
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
        ];

        $isHealthy = collect($checks)->every(fn (array $check) => $check['status'] === 'ok');

        return response()->json([
            'status' => $isHealthy ? 'ok' : 'degraded',
            'checks' => $checks,
        ], $isHealthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');

            return [
                'status' => 'ok',
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'error' => 'database_unreachable',
            ];
        }
    }

    /**
     * @return array{status: string, error?: string, age_seconds?: int}
     */
    private function checkQueue(): array
    {
        try {
            $connection = config('queue.default');

            // Touch the connection to ensure configuration is valid.
            app('queue')->connection($connection);

            $age = $this->oldestAvailableJobAgeInSeconds($connection);

            if ($age !== null && $age > (int) config('health.queue_max_age_seconds')) {
                return [
                    'status' => 'failed',
                    'error' => 'queue_stalled',
                    'age_seconds' => $age,
                ];
            }

            return [
                'status' => 'ok',
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'error' => 'queue_unavailable',
            ];
        }
    }

    /**
     * Leeftijd van de oudste job die nu uitgevoerd had kunnen worden. Jobs met een
     * `available_at` in de toekomst (delay) en jobs die een worker net heeft
     * gereserveerd tellen niet mee; een reservering ouder dan `retry_after` wel,
     * want die wordt pas weer opgepakt als er een worker draait. Alleen voor de
     * database-driver, andere drivers hebben hier geen zicht op.
     */
    private function oldestAvailableJobAgeInSeconds(string $connection): ?int
    {
        $config = config("queue.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'database') {
            return null;
        }

        $now = now()->getTimestamp();
        $retryAfter = (int) ($config['retry_after'] ?? 90);

        $oldest = DB::connection($config['connection'] ?? null)
            ->table($config['table'] ?? 'jobs')
            ->where('queue', $config['queue'] ?? 'default')
            ->where('available_at', '<=', $now)
            ->where(function ($query) use ($now, $retryAfter): void {
                $query->whereNull('reserved_at')
                    ->orWhere('reserved_at', '<=', $now - $retryAfter);
            })
            ->min('available_at');

        return $oldest === null ? null : max(0, $now - (int) $oldest);
    }

    private function checkStorage(): array
    {
        try {
            $disk = config('filesystems.default');
            $path = 'healthchecks/storage-check.txt';

            Storage::disk($disk)->put($path, 'ok');
            Storage::disk($disk)->delete($path);

            return [
                'status' => 'ok',
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'error' => 'storage_unwritable',
            ];
        }
    }
}

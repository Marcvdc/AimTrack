<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    private function checkQueue(): array
    {
        try {
            $connection = config('queue.default');

            // Touch the connection to ensure configuration is valid.
            app('queue')->connection($connection);

            $age = $this->oldestRunnableJobAgeInSeconds($connection);

            if ($age !== null && $age > (int) config('health.queue_max_age_seconds')) {
                Log::warning('Queue stilgevallen: oudste uitvoerbare job wacht al te lang.', [
                    'age_seconds' => $age,
                ]);

                return [
                    'status' => 'failed',
                    'error' => 'queue_stalled',
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
     * Seconden sinds het moment waarop de oudste job uitgevoerd had kunnen worden.
     * Jobs met een `available_at` in de toekomst (delay) en jobs die een worker net
     * heeft gereserveerd tellen niet mee. Een reservering ouder dan `retry_after`
     * telt wel, vanaf het moment dat die verliep: dat is een gecrashte worker.
     * Alle queue-namen tellen mee, want de worker draait zonder `--queue`.
     * Alleen voor de database-driver, andere drivers geven hier geen zicht op.
     */
    private function oldestRunnableJobAgeInSeconds(string $connection): ?int
    {
        $config = config("queue.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'database') {
            return null;
        }

        $now = now()->getTimestamp();
        $retryAfter = (int) ($config['retry_after'] ?? 90);

        $jobs = fn () => DB::connection($config['connection'] ?? null)
            ->table($config['table'] ?? 'jobs');

        $unreservedSince = $jobs()
            ->whereNull('reserved_at')
            ->where('available_at', '<=', $now)
            ->min('available_at');

        $expiredReservation = $jobs()
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '<=', $now - $retryAfter)
            ->where('available_at', '<=', $now)
            ->min('reserved_at');

        $runnableSince = array_filter([
            $unreservedSince === null ? null : (int) $unreservedSince,
            $expiredReservation === null ? null : (int) $expiredReservation + $retryAfter,
        ], fn (?int $timestamp): bool => $timestamp !== null);

        return $runnableSince === [] ? null : max(0, $now - min($runnableSince));
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

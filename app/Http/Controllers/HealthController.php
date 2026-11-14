<?php

namespace App\Http\Controllers;

use App\Services\QueueLivenessService;
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

            $age = app(QueueLivenessService::class)->oldestRunnableJobAgeInSeconds($connection);

            if ($age !== null && $age > $this->queueMaxAgeSeconds()) {
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

    private function queueMaxAgeSeconds(): int
    {
        $configured = (int) config('health.queue_max_age_seconds');

        return $configured > 0 ? $configured : 300;
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

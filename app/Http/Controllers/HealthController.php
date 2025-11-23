<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

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
            $queue = config("queue.connections.{$connection}.queue", 'default');

            // Touch the connection to ensure configuration is valid.
            app('queue')->connection($connection);

            return [
                'status' => 'ok',
                'connection' => $connection,
                'queue' => $queue,
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'error' => 'queue_unavailable',
            ];
        }
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
                'disk' => $disk,
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'error' => 'storage_unwritable',
            ];
        }
    }
}

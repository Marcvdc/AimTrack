<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Meet hoe lang de oudste uitvoerbare job in de database-queue al wacht, als
 * maat voor of er nog een worker draait (issue #187).
 */
class QueueLivenessService
{
    /**
     * Seconden sinds het moment waarop de oudste job uitgevoerd had kunnen worden,
     * of null als er niets wacht of de driver geen zicht geeft.
     *
     * Jobs met een `available_at` in de toekomst (delay) en jobs die een worker net
     * heeft gereserveerd tellen niet mee. Een reservering ouder dan `retry_after`
     * telt wel, vanaf het moment dat die verliep: dat is een gecrashte worker.
     * Alle queue-namen tellen mee, want de worker draait zonder `--queue`.
     */
    public function oldestRunnableJobAgeInSeconds(?string $connection = null): ?int
    {
        $connection ??= config('queue.default');
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
}

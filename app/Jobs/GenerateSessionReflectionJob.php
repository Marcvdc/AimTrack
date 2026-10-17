<?php

namespace App\Jobs;

use App\Exceptions\Ai\AiUnavailableException;
use App\Models\Session;
use App\Services\Ai\ShooterCoach;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateSessionReflectionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * Wachttijd in seconden voor de tweede en derde poging.
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300];

    public function __construct(public Session $session) {}

    /**
     * Een tijdelijke fout gaat door naar de queue, die de job opnieuw probeert.
     * Een fout die niet vanzelf overgaat (geen key, 401/403) faalt de job direct.
     *
     * @throws AiUnavailableException
     */
    public function handle(): void
    {
        try {
            ShooterCoach::make()->generateSessionReflection($this->session->fresh(['sessionWeapons.weapon']));
        } catch (AiUnavailableException $exception) {
            if (! $exception->isRetryable()) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('AI: genereren sessiereflectie mislukt', [
            'session_id' => $this->session->id,
            'exception' => $exception !== null ? $exception::class : null,
            'error' => $exception?->getMessage(),
        ]);
    }
}

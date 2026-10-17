<?php

namespace App\Jobs;

use App\Exceptions\Ai\AiUnavailableException;
use App\Models\Weapon;
use App\Services\Ai\ShooterCoach;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateWeaponInsightJob implements ShouldQueue
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

    public function __construct(public Weapon $weapon) {}

    /**
     * Een tijdelijke fout gaat door naar de queue, die de job opnieuw probeert.
     * Een fout die niet vanzelf overgaat (geen key, 401/403) faalt de job direct.
     *
     * @throws AiUnavailableException
     */
    public function handle(): void
    {
        try {
            ShooterCoach::make()->generateWeaponInsight($this->weapon->fresh(['sessionWeapons.session']));
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
        Log::error('AI: genereren wapeninzichten mislukt', [
            'weapon_id' => $this->weapon->id,
            'exception' => $exception !== null ? $exception::class : null,
            'error' => $exception?->getMessage(),
        ]);
    }
}

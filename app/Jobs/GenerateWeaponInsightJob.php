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

    /**
     * Langste poging: 3 HTTP-pogingen van max. 20 s plus 1 s en 3 s backoff
     * in ShooterCoach is 64 s. Ruim daarboven, en onder retry_after (90 s)
     * van de database-queue zodat een lopende poging niet dubbel wordt opgepakt.
     */
    public int $timeout = 80;

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

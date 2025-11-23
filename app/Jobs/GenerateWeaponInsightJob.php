<?php

namespace App\Jobs;

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

    public function __construct(public Weapon $weapon)
    {
    }

    public function handle(): void
    {
        try {
            ShooterCoach::make()->generateWeaponInsight($this->weapon->fresh(['sessionWeapons.session']));
        } catch (Throwable $exception) {
            Log::error('AI: genereren wapeninzichten mislukt', [
                'weapon_id' => $this->weapon->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}

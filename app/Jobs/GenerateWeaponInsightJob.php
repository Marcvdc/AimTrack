<?php

namespace App\Jobs;

use App\Models\Weapon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        // TODO: invullen met AI-call via ShooterCoach service.
    }
}

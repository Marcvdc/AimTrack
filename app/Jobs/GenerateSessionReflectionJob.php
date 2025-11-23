<?php

namespace App\Jobs;

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

    public function __construct(public Session $session)
    {
    }

    public function handle(): void
    {
        try {
            ShooterCoach::make()->generateSessionReflection($this->session->fresh(['sessionWeapons.weapon']));
        } catch (Throwable $exception) {
            Log::error('AI: genereren sessiereflectie mislukt', [
                'session_id' => $this->session->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}

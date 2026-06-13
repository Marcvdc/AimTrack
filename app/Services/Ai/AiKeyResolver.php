<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Models\Vereniging;

class AiKeyResolver
{
    /**
     * De actieve Claude-key voor een gebruiker.
     * Resolutie (#95): user-key -> actieve verenigings-key -> null.
     */
    public function forUser(?User $user): ?string
    {
        $key = $user?->anthropic_api_key;

        if (filled($key)) {
            return $key;
        }

        return $this->forVereniging($user?->activeVereniging);
    }

    /**
     * De gedeelde Claude-key van een vereniging, of null.
     */
    public function forVereniging(?Vereniging $vereniging): ?string
    {
        $key = $vereniging?->anthropic_api_key;

        return filled($key) ? $key : null;
    }

    public function forCurrentUser(): ?string
    {
        $user = auth()->user();

        return $user instanceof User ? $this->forUser($user) : null;
    }
}

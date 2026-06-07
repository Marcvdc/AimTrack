<?php

namespace App\Services\Ai;

use App\Models\User;

class AiKeyResolver
{
    /**
     * De actieve Claude-key voor een gebruiker.
     * Fase 1: alleen de eigen key. Fase 2 (#95) breidt dit uit met een
     * verenigings-key fallback: user-key -> vereniging-key -> null.
     */
    public function forUser(?User $user): ?string
    {
        $key = $user?->anthropic_api_key;

        return filled($key) ? $key : null;
    }

    public function forCurrentUser(): ?string
    {
        $user = auth()->user();

        return $user instanceof User ? $this->forUser($user) : null;
    }
}

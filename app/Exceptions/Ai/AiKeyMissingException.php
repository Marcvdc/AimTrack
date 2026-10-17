<?php

namespace App\Exceptions\Ai;

/**
 * Er is geen Claude API-key voor de gebruiker (en geen verenigings-key).
 * Opnieuw proberen heeft geen zin tot de key is ingesteld.
 */
class AiKeyMissingException extends AiUnavailableException
{
    public function __construct()
    {
        parent::__construct('Geen Claude API-key ingesteld voor deze gebruiker.');
    }

    public function isRetryable(): bool
    {
        return false;
    }
}

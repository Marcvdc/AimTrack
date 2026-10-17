<?php

namespace App\Exceptions\Ai;

use RuntimeException;
use Throwable;

/**
 * De AI-provider gaf geen bruikbaar antwoord. Zonder HTTP-status gaat het
 * om een netwerkfout of timeout.
 */
class AiUnavailableException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Een netwerkfout, 429 of 5xx is tijdelijk en mag opnieuw geprobeerd
     * worden. Een andere 4xx (zoals 401/403 bij een ongeldige key) niet.
     */
    public function isRetryable(): bool
    {
        return self::isRetryableStatus($this->status);
    }

    public static function isRetryableStatus(?int $status): bool
    {
        return $status === null || $status === 429 || $status >= 500;
    }
}

<?php

namespace App\Services\Vision;

/**
 * Wat een analyse aan tokens heeft gekost.
 *
 * Dit wordt vastgelegd omdat schatten niet werkt. Bij het denkwerk van een
 * vision-model zitten de denk-tokens in de output, en die zijn op Opus 5 vijf keer
 * zo duur als input. Een run van tientallen foto's kan daardoor een veelvoud kosten
 * van wat je op de achterkant van een envelop uitrekent, en dat merk je pas als het
 * tegoed op is.
 */
final readonly class TokenUsage
{
    public function __construct(
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cacheReadTokens = 0,
        public int $cacheCreationTokens = 0,
    ) {}

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function fromArray(?array $payload): self
    {
        if (! is_array($payload)) {
            return new self;
        }

        return new self(
            inputTokens: (int) ($payload['input_tokens'] ?? 0),
            outputTokens: (int) ($payload['output_tokens'] ?? 0),
            cacheReadTokens: (int) ($payload['cache_read_input_tokens'] ?? 0),
            cacheCreationTokens: (int) ($payload['cache_creation_input_tokens'] ?? 0),
        );
    }

    public function plus(self $other): self
    {
        return new self(
            inputTokens: $this->inputTokens + $other->inputTokens,
            outputTokens: $this->outputTokens + $other->outputTokens,
            cacheReadTokens: $this->cacheReadTokens + $other->cacheReadTokens,
            cacheCreationTokens: $this->cacheCreationTokens + $other->cacheCreationTokens,
        );
    }

    /**
     * Geschatte kosten in dollars. De prijzen staan in config/vision.php en gelden
     * per miljoen tokens; cache-lezingen rekenen tegen een tiende van de invoerprijs.
     */
    public function estimatedCost(float $inputPerMillion, float $outputPerMillion): float
    {
        return ($this->inputTokens + $this->cacheCreationTokens) / 1_000_000 * $inputPerMillion
            + $this->cacheReadTokens / 1_000_000 * $inputPerMillion * 0.1
            + $this->outputTokens / 1_000_000 * $outputPerMillion;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'cache_read_tokens' => $this->cacheReadTokens,
            'cache_creation_tokens' => $this->cacheCreationTokens,
        ];
    }
}

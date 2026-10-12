<?php

namespace App\Services\Vision;

/**
 * Een donkere of ronde vlek die het model bewust NIET als schot telt: een plakker,
 * een gedrukt ringcijfer, een scheur of tape.
 *
 * Dit is de kern van de foutdiagnose. Zonder deze lijst is niet te zien of een
 * gemist schot echt gemist is of bewust is afgewezen, en dat is precies het
 * onderscheid dat bij het afstellen van de prompt nodig is. De positie is in
 * pixels van de verkleinde foto, want het gaat hier om de foto en niet om de roos.
 */
final readonly class RejectedCandidate
{
    public function __construct(
        public int $xPixels,
        public int $yPixels,
        public string $kind,
        public string $reason,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            xPixels: (int) ($payload['x_px'] ?? 0),
            yPixels: (int) ($payload['y_px'] ?? 0),
            kind: is_string($payload['kind'] ?? null) ? $payload['kind'] : 'other',
            reason: is_string($payload['reason'] ?? null) ? $payload['reason'] : '',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'x_px' => $this->xPixels,
            'y_px' => $this->yPixels,
            'kind' => $this->kind,
            'reason' => $this->reason,
        ];
    }
}

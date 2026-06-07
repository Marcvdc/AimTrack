<?php

declare(strict_types=1);

namespace App\Enums;

enum TrainingGoalSource: string
{
    case Ai = 'ai';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Ai => 'AI-suggestie',
            self::Manual => 'Zelf toegevoegd',
        };
    }
}

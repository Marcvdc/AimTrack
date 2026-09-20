<?php

namespace App\Enums;

enum Deviation: string
{
    case LEFT = 'left';
    case RIGHT = 'right';
    case HIGH = 'high';
    case LOW = 'low';
    case NONE = 'none';

    public function label(): string
    {
        return match ($this) {
            self::LEFT => 'Links',
            self::RIGHT => 'Rechts',
            self::HIGH => 'Hoog',
            self::LOW => 'Laag',
            self::NONE => 'Geen',
        };
    }
}

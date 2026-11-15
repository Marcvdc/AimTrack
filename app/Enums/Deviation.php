<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Deviation: string implements HasLabel
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

    /**
     * Zie WeaponType::getLabel(): Filament gebruikt uitsluitend deze interface
     * om een enum-waarde als label te tonen.
     */
    public function getLabel(): string
    {
        return $this->label();
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WeaponType: string implements HasLabel
{
    case AIR_PISTOL = 'luchtpistool';
    case AIR_RIFLE = 'luchtgeweer';
    case PISTOL = 'pistool';
    case RIFLE = 'geweer';
    case CARBINE = 'karabijn';
    case REVOLVER = 'revolver';
    case SHOTGUN = 'hagelgeweer';
    case OTHER = 'overig';

    public function label(): string
    {
        return match ($this) {
            self::AIR_PISTOL => 'Luchtpistool',
            self::AIR_RIFLE => 'Luchtgeweer',
            self::PISTOL => 'Pistool',
            self::RIFLE => 'Geweer',
            self::CARBINE => 'Karabijn',
            self::REVOLVER => 'Revolver',
            self::SHOTGUN => 'Hagelgeweer',
            self::OTHER => 'Overig',
        };
    }

    /**
     * Filament leest het label alleen via deze interface. Zonder deze methode
     * valt een badge of tekstkolom terug op de rauwe waarde, en dan staat er
     * "luchtpistool" in de wapenlijst naast een filter dat "Luchtpistool" toont.
     */
    public function getLabel(): string
    {
        return $this->label();
    }
}

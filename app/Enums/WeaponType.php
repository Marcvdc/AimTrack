<?php

namespace App\Enums;

enum WeaponType: string
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
}

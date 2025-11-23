<?php

namespace App\Enums;

enum WeaponType: string
{
    case PISTOL = 'pistool';
    case RIFLE = 'geweer';
    case CARBINE = 'karabijn';
    case REVOLVER = 'revolver';
    case SHOTGUN = 'hagelgeweer';
    case OTHER = 'overig';
}

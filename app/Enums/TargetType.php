<?php

namespace App\Enums;

enum TargetType: string
{
    case Kkp25m = 'kkp_25m';
    case Gkp25m = 'gkp_25m';
    case Kkg50m = 'kkg_50m';
    case Kkg100m = 'kkg_100m';
    case Gkg100m = 'gkg_100m';
}

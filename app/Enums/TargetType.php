<?php

namespace App\Enums;

/**
 * De KNSA/ISSF-rozen waarop AimTrack schoten kan bepalen.
 *
 * De waarden komen overeen met de sleutels van TARGET_SPECS in de Python-service,
 * zodat een meetset die daar gelabeld is hier zonder vertaling bruikbaar blijft.
 */
enum TargetType: string
{
    case KKP_25M = 'kkp_25m';
    case GKP_25M = 'gkp_25m';
    case KKG_50M = 'kkg_50m';
    case KKG_100M = 'kkg_100m';
    case GKG_100M = 'gkg_100m';

    /**
     * De naam zoals die in de prompt aan het model wordt meegegeven.
     */
    public function label(): string
    {
        return match ($this) {
            self::KKP_25M => '25m KKP',
            self::GKP_25M => '25m GKP',
            self::KKG_50M => '50m KKG',
            self::KKG_100M => '100m KKG',
            self::GKG_100M => '100m GKG',
        };
    }

    public function distanceMeters(): int
    {
        return match ($this) {
            self::KKP_25M, self::GKP_25M => 25,
            self::KKG_50M => 50,
            self::KKG_100M, self::GKG_100M => 100,
        };
    }
}

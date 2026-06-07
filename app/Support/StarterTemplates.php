<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\WeaponType;

/**
 * Starter-sjablonen voor de "geen wapens"-empty-state. Drie veelvoorkomende
 * wapenconfiguraties die nieuwe gebruikers in één klik kunnen aanmaken.
 *
 * Beslissing PLAN-fase-2 (punt 5): alle drie mappen op WeaponType::PISTOL —
 * AimTrack is een schietvereniging-app, geen luchtsport-app. Onderscheid
 * zit puur in caliber + label.
 */
final class StarterTemplates
{
    /**
     * @return array<int, array{key: string, label: string, caliber: string, weapon_type: WeaponType, popular: bool}>
     */
    public static function weapons(): array
    {
        return [
            [
                'key' => 'luchtpistool',
                'label' => 'Luchtpistool',
                'caliber' => '4.5 mm',
                'weapon_type' => WeaponType::PISTOL,
                'popular' => true,
            ],
            [
                'key' => 'pistool-9mm',
                'label' => 'Pistool',
                'caliber' => '9×19 mm',
                'weapon_type' => WeaponType::PISTOL,
                'popular' => false,
            ],
            [
                'key' => 'vrij-pistool',
                'label' => 'Vrij pistool',
                'caliber' => '.22 LR',
                'weapon_type' => WeaponType::PISTOL,
                'popular' => false,
            ],
        ];
    }

    /**
     * @return array{key: string, label: string, caliber: string, weapon_type: WeaponType, popular: bool}|null
     */
    public static function findWeapon(string $key): ?array
    {
        foreach (self::weapons() as $template) {
            if ($template['key'] === $key) {
                return $template;
            }
        }

        return null;
    }
}

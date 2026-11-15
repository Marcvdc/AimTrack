<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\WeaponType;

/**
 * Starter-sjablonen voor de "geen wapens"-empty-state. Vier veelvoorkomende
 * wapenconfiguraties die nieuwe gebruikers in één klik kunnen aanmaken.
 *
 * Luchtdruk heeft sinds issue #145 een eigen WeaponType. De eerdere beslissing
 * om luchtpistool op WeaponType::PISTOL te mappen is daarmee vervallen: de
 * jeugd- en opleidingslijn draait volledig op luchtdruk, dus het type-filter,
 * de groepering per wapentype en de disciplinevoortgang moeten die schutters
 * apart kunnen tonen.
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
                'weapon_type' => WeaponType::AIR_PISTOL,
                'popular' => true,
            ],
            [
                'key' => 'luchtgeweer',
                'label' => 'Luchtgeweer',
                'caliber' => '4.5 mm',
                'weapon_type' => WeaponType::AIR_RIFLE,
                'popular' => false,
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

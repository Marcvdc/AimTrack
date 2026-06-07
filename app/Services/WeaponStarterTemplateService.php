<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\StarterTemplates;

/**
 * Vertaalt een starter-sjabloon sleutel (uit de geen-wapens empty state)
 * naar de form-prefill waarden voor CreateWeapon. Houdt de mapping-logica
 * buiten de Filament Page (CLAUDE.md regel 12) en doet bewust geen enkele
 * database-write — de caliber-Select toont starter-kalibers als vaste
 * opties, dus er hoeft geen AmmoType-rij aangemaakt te worden om de
 * prefill te laten renderen.
 */
final class WeaponStarterTemplateService
{
    /**
     * @return array{name: string, weapon_type: string, caliber: string}|null
     */
    public function prefillData(mixed $key): ?array
    {
        if (! is_string($key) || $key === '') {
            return null;
        }

        $template = StarterTemplates::findWeapon($key);

        if ($template === null) {
            return null;
        }

        return [
            'name' => $template['label'],
            'weapon_type' => $template['weapon_type']->value,
            'caliber' => $template['caliber'],
        ];
    }
}

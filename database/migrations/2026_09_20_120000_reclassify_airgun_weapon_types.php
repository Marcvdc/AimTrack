<?php

use App\Enums\WeaponType;
use App\Models\Weapon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Migrations\Migration;

/**
 * Luchtpistool en luchtgeweer bestonden niet als WeaponType, dus zijn ze tot nu
 * toe als pistool of geweer weggeschreven (onder meer door het luchtpistool-
 * starter-sjabloon). Deze migratie zet die rijen alsnog op het juiste type.
 *
 * De herkenning is bewust smal: alleen een naam die het met zoveel woorden zegt,
 * of een kaliber dat uitsluitend bij luchtdruk voorkomt. Een vrij pistool in
 * .22 LR blijft dus een pistool, en een geweer in 5.56 mm blijft een geweer:
 * de kalibercheck eist een cijfergrens, zodat 5.5 niet op 5.56 aanslaat.
 */
return new class extends Migration
{
    private const AIR_CALIBER_PATTERN = '/(?<!\d)(4[.,]5|5[.,]5|\.177)(?!\d)/';

    public function up(): void
    {
        $this->reclassify(WeaponType::PISTOL, WeaponType::AIR_PISTOL, 'luchtpistool');
        $this->reclassify(WeaponType::RIFLE, WeaponType::AIR_RIFLE, 'luchtgeweer');
    }

    /**
     * Zet alle luchtdrukwapens terug op pistool of geweer. Dat raakt ook wapens
     * die na deze migratie bewust als luchtdruk zijn aangemaakt, want het type
     * bestaat na een rollback niet meer.
     */
    public function down(): void
    {
        Weapon::query()
            ->where('weapon_type', WeaponType::AIR_PISTOL->value)
            ->update(['weapon_type' => WeaponType::PISTOL->value]);

        Weapon::query()
            ->where('weapon_type', WeaponType::AIR_RIFLE->value)
            ->update(['weapon_type' => WeaponType::RIFLE->value]);
    }

    private function reclassify(WeaponType $from, WeaponType $to, string $nameNeedle): void
    {
        Weapon::query()
            ->where('weapon_type', $from->value)
            ->select(['id', 'name', 'caliber'])
            ->chunkById(200, function (Collection $weapons) use ($to, $nameNeedle): void {
                $ids = $weapons
                    ->filter(fn (Weapon $weapon): bool => $this->isAirgun($weapon, $nameNeedle))
                    ->pluck('id')
                    ->all();

                if ($ids === []) {
                    return;
                }

                Weapon::query()
                    ->whereIn('id', $ids)
                    ->update(['weapon_type' => $to->value]);
            });
    }

    private function isAirgun(Weapon $weapon, string $nameNeedle): bool
    {
        if (str_contains(mb_strtolower((string) $weapon->name), $nameNeedle)) {
            return true;
        }

        return preg_match(self::AIR_CALIBER_PATTERN, (string) $weapon->caliber) === 1;
    }
};

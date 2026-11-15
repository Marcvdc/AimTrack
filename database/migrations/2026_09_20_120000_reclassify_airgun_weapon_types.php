<?php

use App\Enums\WeaponType;
use App\Models\Weapon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Luchtpistool en luchtgeweer bestonden niet als WeaponType, dus zijn ze tot nu
 * toe als pistool of geweer weggeschreven (onder meer door het luchtpistool-
 * starter-sjabloon). Deze migratie zet die rijen alsnog op het juiste type.
 *
 * De herkenning is bewust smal: alleen een naam die het met zoveel woorden zegt,
 * of een kaliber dat uitsluitend bij luchtdruk voorkomt. Een vrij pistool in
 * .22 LR blijft dus een pistool, en een geweer in 5.56 mm blijft een geweer:
 * de kalibercheck eist een cijfergrens, zodat 5.5 niet op 5.56 aanslaat.
 *
 * Beide richtingen loggen hoeveel rijen ze hebben geraakt. Dit draait over
 * bestaande gebruikersdata op een heuristiek, dus achteraf moet vast te stellen
 * zijn wat er is omgezet zonder de database ernaast te leggen.
 */
return new class extends Migration
{
    private const AIR_CALIBER_PATTERN = '/(?<!\d)(4[.,]5|5[.,]5|\.177)(?!\d)/';

    public function up(): void
    {
        $airPistols = $this->reclassify(WeaponType::PISTOL, WeaponType::AIR_PISTOL, 'luchtpistool');
        $airRifles = $this->reclassify(WeaponType::RIFLE, WeaponType::AIR_RIFLE, 'luchtgeweer');

        Log::info('Wapens geherclassificeerd naar een luchtdruktype', [
            'migration' => 'reclassify_airgun_weapon_types',
            'direction' => 'up',
            WeaponType::AIR_PISTOL->value => $airPistols,
            WeaponType::AIR_RIFLE->value => $airRifles,
        ]);
    }

    /**
     * Zet alle luchtdrukwapens terug op pistool of geweer. Dat raakt ook wapens
     * die na deze migratie bewust als luchtdruk zijn aangemaakt, want het type
     * bestaat na een rollback niet meer.
     */
    public function down(): void
    {
        $pistols = Weapon::query()
            ->where('weapon_type', WeaponType::AIR_PISTOL->value)
            ->update(['weapon_type' => WeaponType::PISTOL->value]);

        $rifles = Weapon::query()
            ->where('weapon_type', WeaponType::AIR_RIFLE->value)
            ->update(['weapon_type' => WeaponType::RIFLE->value]);

        Log::info('Luchtdruktypes teruggezet op pistool en geweer', [
            'migration' => 'reclassify_airgun_weapon_types',
            'direction' => 'down',
            WeaponType::PISTOL->value => $pistols,
            WeaponType::RIFLE->value => $rifles,
        ]);
    }

    /**
     * @return int het aantal omgezette rijen
     */
    private function reclassify(WeaponType $from, WeaponType $to, string $nameNeedle): int
    {
        $converted = 0;

        Weapon::query()
            ->where('weapon_type', $from->value)
            ->select(['id', 'name', 'caliber'])
            ->chunkById(200, function (Collection $weapons) use ($to, $nameNeedle, &$converted): void {
                $ids = $weapons
                    ->filter(fn (Weapon $weapon): bool => $this->isAirgun($weapon, $nameNeedle))
                    ->pluck('id')
                    ->all();

                if ($ids === []) {
                    return;
                }

                $converted += Weapon::query()
                    ->whereIn('id', $ids)
                    ->update(['weapon_type' => $to->value]);
            });

        return $converted;
    }

    private function isAirgun(Weapon $weapon, string $nameNeedle): bool
    {
        if (str_contains(mb_strtolower((string) $weapon->name), $nameNeedle)) {
            return true;
        }

        return preg_match(self::AIR_CALIBER_PATTERN, (string) $weapon->caliber) === 1;
    }
};

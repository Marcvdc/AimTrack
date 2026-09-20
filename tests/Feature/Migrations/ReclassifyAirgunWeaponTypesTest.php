<?php

declare(strict_types=1);

use App\Enums\WeaponType;
use App\Models\User;
use App\Models\Weapon;

/**
 * De migratie draait al bij het opzetten van de testdatabase, dus we laden het
 * migratie-object opnieuw en roepen up() aan op verse rijen.
 */
function reclassifyAirgunMigration(): object
{
    return require __DIR__.'/../../../database/migrations/2026_09_20_120000_reclassify_airgun_weapon_types.php';
}

it('zet een als pistool opgeslagen luchtpistool om naar het nieuwe type', function (): void {
    $weapon = Weapon::factory()->for(User::factory())->create([
        'name' => 'Luchtpistool',
        'weapon_type' => WeaponType::PISTOL,
        'caliber' => '4.5 mm',
    ]);

    reclassifyAirgunMigration()->up();

    expect($weapon->fresh()->weapon_type)->toBe(WeaponType::AIR_PISTOL);
});

it('zet een als geweer opgeslagen luchtgeweer om naar het nieuwe type', function (): void {
    $weapon = Weapon::factory()->for(User::factory())->create([
        'name' => 'Walther LG400',
        'weapon_type' => WeaponType::RIFLE,
        'caliber' => '4,5 mm',
    ]);

    reclassifyAirgunMigration()->up();

    expect($weapon->fresh()->weapon_type)->toBe(WeaponType::AIR_RIFLE);
});

it('laat een echt vuurwapen ongemoeid', function (string $naam, string $kaliber, WeaponType $type): void {
    $weapon = Weapon::factory()->for(User::factory())->create([
        'name' => $naam,
        'weapon_type' => $type,
        'caliber' => $kaliber,
    ]);

    reclassifyAirgunMigration()->up();

    expect($weapon->fresh()->weapon_type)->toBe($type);
})->with([
    'glock 9mm' => ['Glock 17', '9×19 mm', WeaponType::PISTOL],
    'vrij pistool .22 LR' => ['Vrij pistool', '.22 LR', WeaponType::PISTOL],
    'geweer 5.56 mm valt niet op de 5.5-check' => ['AR-15', '5.56 mm', WeaponType::RIFLE],
    'geweer .308' => ['Tikka T3x', '.308 Win', WeaponType::RIFLE],
]);

it('raakt een revolver met een luchtachtig kaliber niet, want alleen pistool en geweer worden bekeken', function (): void {
    $weapon = Weapon::factory()->for(User::factory())->create([
        'name' => 'Revolver',
        'weapon_type' => WeaponType::REVOLVER,
        'caliber' => '4.5 mm',
    ]);

    reclassifyAirgunMigration()->up();

    expect($weapon->fresh()->weapon_type)->toBe(WeaponType::REVOLVER);
});

it('is herhaalbaar en laat een al omgezette rij staan', function (): void {
    $weapon = Weapon::factory()->for(User::factory())->create([
        'name' => 'Steyr LP50',
        'weapon_type' => WeaponType::AIR_PISTOL,
        'caliber' => '4.5 mm',
    ]);

    reclassifyAirgunMigration()->up();

    expect($weapon->fresh()->weapon_type)->toBe(WeaponType::AIR_PISTOL);
});

it('draait de omzetting terug met down()', function (): void {
    $pistool = Weapon::factory()->for(User::factory())->create([
        'name' => 'Steyr LP50',
        'weapon_type' => WeaponType::AIR_PISTOL,
        'caliber' => '4.5 mm',
    ]);
    $geweer = Weapon::factory()->for(User::factory())->create([
        'name' => 'Walther LG400',
        'weapon_type' => WeaponType::AIR_RIFLE,
        'caliber' => '4.5 mm',
    ]);

    reclassifyAirgunMigration()->down();

    expect($pistool->fresh()->weapon_type)->toBe(WeaponType::PISTOL)
        ->and($geweer->fresh()->weapon_type)->toBe(WeaponType::RIFLE);
});

<?php

declare(strict_types=1);

use App\Enums\WeaponType;
use App\Models\User;
use App\Models\Weapon;
use Illuminate\Support\Facades\Log;

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
    'randvuur .17 HMR valt niet op de .177-check' => ['CZ 457', '.17 HMR', WeaponType::RIFLE],
    'pistool 5.56 blijft buiten de 5.5-check' => ['AR-pistol', '5.56 NATO', WeaponType::PISTOL],
]);

it('herkent een luchtdrukkaliber ook als de naam niets prijsgeeft', function (string $kaliber, WeaponType $van, WeaponType $naar): void {
    $weapon = Weapon::factory()->for(User::factory())->create([
        'name' => 'Clubwapen 3',
        'weapon_type' => $van,
        'caliber' => $kaliber,
    ]);

    reclassifyAirgunMigration()->up();

    expect($weapon->fresh()->weapon_type)->toBe($naar);
})->with([
    '4.5 mm' => ['4.5 mm', WeaponType::PISTOL, WeaponType::AIR_PISTOL],
    '4,5 mm' => ['4,5 mm', WeaponType::RIFLE, WeaponType::AIR_RIFLE],
    '5.5 mm' => ['5.5 mm', WeaponType::RIFLE, WeaponType::AIR_RIFLE],
    '5,5 mm' => ['5,5 mm', WeaponType::PISTOL, WeaponType::AIR_PISTOL],
    '.177' => ['.177', WeaponType::PISTOL, WeaponType::AIR_PISTOL],
    '.177 cal' => ['.177 cal', WeaponType::RIFLE, WeaponType::AIR_RIFLE],
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

it('logt hoeveel rijen er per type zijn omgezet', function (): void {
    Log::spy();

    Weapon::factory()->for(User::factory())->create([
        'name' => 'Steyr luchtpistool',
        'weapon_type' => WeaponType::PISTOL,
        'caliber' => '4.5 mm',
    ]);
    Weapon::factory()->for(User::factory())->create([
        'name' => 'Glock 17',
        'weapon_type' => WeaponType::PISTOL,
        'caliber' => '9x19 mm',
    ]);

    reclassifyAirgunMigration()->up();

    Log::shouldHaveReceived('info')
        ->withArgs(function ($bericht, $context): bool {
            return is_array($context)
                && ($context['direction'] ?? null) === 'up'
                && ($context[WeaponType::AIR_PISTOL->value] ?? null) === 1
                && ($context[WeaponType::AIR_RIFLE->value] ?? null) === 0;
        })
        ->once();
});

it('logt hoeveel rijen down() heeft teruggezet', function (): void {
    Log::spy();

    Weapon::factory()->for(User::factory())->create([
        'name' => 'Steyr LP50',
        'weapon_type' => WeaponType::AIR_PISTOL,
        'caliber' => '4.5 mm',
    ]);

    reclassifyAirgunMigration()->down();

    Log::shouldHaveReceived('info')
        ->withArgs(function ($bericht, $context): bool {
            return is_array($context)
                && ($context['direction'] ?? null) === 'down'
                && ($context[WeaponType::PISTOL->value] ?? null) === 1
                && ($context[WeaponType::RIFLE->value] ?? null) === 0;
        })
        ->once();
});

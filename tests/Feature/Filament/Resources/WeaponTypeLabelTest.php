<?php

declare(strict_types=1);

use App\Enums\WeaponType;
use App\Filament\Resources\WeaponResource\Pages\ListWeapons;
use App\Models\User;
use App\Models\Weapon;
use Livewire\Livewire;

/**
 * De wapenlijst rendert het type via een gewone badge-kolom zonder eigen
 * formatter. Filament pakt daar alleen een label op als de enum HasLabel
 * implementeert; anders staat er "luchtpistool" in de lijst naast een filter
 * dat "Luchtpistool" toont.
 */
test('de wapenlijst toont het nederlandse label in plaats van de rauwe enum-waarde', function (): void {
    $user = User::factory()->create();
    $luchtpistool = Weapon::factory()->for($user)->create([
        'name' => 'Steyr LP50',
        'weapon_type' => WeaponType::AIR_PISTOL,
        'caliber' => '4.5 mm',
    ]);
    $luchtgeweer = Weapon::factory()->for($user)->create([
        'name' => 'Walther LG400',
        'weapon_type' => WeaponType::AIR_RIFLE,
        'caliber' => '4.5 mm',
    ]);
    $pistool = Weapon::factory()->for($user)->create([
        'name' => 'Glock 17',
        'weapon_type' => WeaponType::PISTOL,
        'caliber' => '9×19 mm',
    ]);

    $this->actingAs($user);

    Livewire::test(ListWeapons::class)
        ->assertTableColumnFormattedStateSet('weapon_type', 'Luchtpistool', $luchtpistool)
        ->assertTableColumnFormattedStateSet('weapon_type', 'Luchtgeweer', $luchtgeweer)
        ->assertTableColumnFormattedStateSet('weapon_type', 'Pistool', $pistool);
});

test('het type-filter toont exact dezelfde labels als de lijst', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    $options = Livewire::test(ListWeapons::class)
        ->instance()
        ->getTable()
        ->getFilter('weapon_type')
        ->getOptions();

    expect($options)->toBe([
        'luchtpistool' => 'Luchtpistool',
        'luchtgeweer' => 'Luchtgeweer',
        'pistool' => 'Pistool',
        'geweer' => 'Geweer',
        'karabijn' => 'Karabijn',
        'revolver' => 'Revolver',
        'hagelgeweer' => 'Hagelgeweer',
        'overig' => 'Overig',
    ]);
});

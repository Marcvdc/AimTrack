<?php

declare(strict_types=1);

use App\Enums\WeaponType;
use App\Filament\Resources\WeaponResource\Pages\ListWeapons;
use App\Models\User;
use App\Models\Weapon;
use Livewire\Livewire;

test('filteren op luchtpistool laat alleen luchtpistolen zien', function (): void {
    $user = User::factory()->create();
    $luchtpistool = Weapon::factory()->for($user)->create([
        'name' => 'Steyr LP50',
        'weapon_type' => WeaponType::AIR_PISTOL,
        'caliber' => '4.5 mm',
    ]);
    $pistool = Weapon::factory()->for($user)->create([
        'name' => 'Glock 17',
        'weapon_type' => WeaponType::PISTOL,
    ]);

    $this->actingAs($user);

    Livewire::test(ListWeapons::class)
        ->assertCanSeeTableRecords([$luchtpistool, $pistool])
        ->filterTable('weapon_type', WeaponType::AIR_PISTOL->value)
        ->assertCanSeeTableRecords([$luchtpistool])
        ->assertCanNotSeeTableRecords([$pistool]);
});

test('filteren op pistool laat een luchtpistool juist buiten beeld', function (): void {
    $user = User::factory()->create();
    $luchtpistool = Weapon::factory()->for($user)->create([
        'name' => 'Steyr LP50',
        'weapon_type' => WeaponType::AIR_PISTOL,
        'caliber' => '4.5 mm',
    ]);
    $pistool = Weapon::factory()->for($user)->create([
        'name' => 'Glock 17',
        'weapon_type' => WeaponType::PISTOL,
    ]);

    $this->actingAs($user);

    Livewire::test(ListWeapons::class)
        ->filterTable('weapon_type', WeaponType::PISTOL->value)
        ->assertCanSeeTableRecords([$pistool])
        ->assertCanNotSeeTableRecords([$luchtpistool]);
});

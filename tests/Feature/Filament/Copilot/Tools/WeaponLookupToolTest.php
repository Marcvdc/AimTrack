<?php

use App\Filament\Copilot\Tools\WeaponLookupTool;
use App\Models\Session;
use App\Models\SessionWeapon;
use App\Models\User;
use App\Models\Weapon;
use Laravel\Ai\Tools\Request;

function makeWeaponLookupTool(User $user): WeaponLookupTool
{
    return (new WeaponLookupTool)
        ->forPanel('admin')
        ->forUser($user)
        ->forTenant(null)
        ->forConversation(null);
}

it('returns weapon details with recente sessie-entries', function () {
    $user = User::factory()->create();
    $weapon = Weapon::factory()->create([
        'user_id' => $user->id,
        'name' => 'Glock 17',
        'caliber' => '9mm',
    ]);
    $session = Session::factory()->create(['user_id' => $user->id, 'date' => now()->subDay()->toDateString()]);
    SessionWeapon::factory()->create([
        'session_id' => $session->id,
        'weapon_id' => $weapon->id,
        'distance_m' => 15,
        'rounds_fired' => 25,
    ]);

    $output = (string) makeWeaponLookupTool($user)->handle(new Request(['weapon_id' => $weapon->id]));

    expect($output)
        ->toContain("Wapen #{$weapon->id}")
        ->toContain('Glock 17')
        ->toContain('9mm')
        ->toContain('15m')
        ->toContain('25 schoten');
});

it('returns a friendly message when weapon_id is missing', function () {
    $user = User::factory()->create();

    $output = (string) makeWeaponLookupTool($user)->handle(new Request);

    expect($output)->toContain('weapon_id ontbreekt');
});

it('refuses to expose another shooters weapon', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $weapon = Weapon::factory()->create(['user_id' => $owner->id]);

    $output = (string) makeWeaponLookupTool($other)->handle(new Request(['weapon_id' => $weapon->id]));

    expect($output)->toContain('Geen wapen gevonden');
});

<?php

use App\Filament\Copilot\Tools\SessionLookupTool;
use App\Models\AiReflection;
use App\Models\Session;
use App\Models\SessionWeapon;
use App\Models\User;
use App\Models\Weapon;
use Laravel\Ai\Tools\Request;

function makeSessionLookupTool(User $user): SessionLookupTool
{
    return (new SessionLookupTool)
        ->forPanel('admin')
        ->forUser($user)
        ->forTenant(null)
        ->forConversation(null);
}

it('returns session details with wapenregels and reflectie', function () {
    $user = User::factory()->create();
    $weapon = Weapon::factory()->create(['user_id' => $user->id, 'name' => 'CZ Shadow 2']);
    $session = Session::factory()->create([
        'user_id' => $user->id,
        'date' => now()->subDay()->toDateString(),
        'range_name' => 'KJ Schietbaan',
    ]);
    SessionWeapon::factory()->create([
        'session_id' => $session->id,
        'weapon_id' => $weapon->id,
        'distance_m' => 25,
        'rounds_fired' => 30,
    ]);
    AiReflection::factory()->create([
        'session_id' => $session->id,
        'summary' => 'Goede sessie',
        'next_focus' => 'Trekkerwerk',
    ]);

    $output = (string) makeSessionLookupTool($user)->handle(new Request(['session_id' => $session->id]));

    expect($output)
        ->toContain("Sessie #{$session->id}")
        ->toContain('CZ Shadow 2')
        ->toContain('25m')
        ->toContain('30 schoten')
        ->toContain('Goede sessie')
        ->toContain('Trekkerwerk');
});

it('returns a friendly message when session_id is missing', function () {
    $user = User::factory()->create();

    $output = (string) makeSessionLookupTool($user)->handle(new Request);

    expect($output)->toContain('session_id ontbreekt');
});

it('refuses to expose another shooters session', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $owner->id]);

    $output = (string) makeSessionLookupTool($other)->handle(new Request(['session_id' => $session->id]));

    expect($output)->toContain('Geen sessie gevonden');
});

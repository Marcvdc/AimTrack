<?php

use App\Filament\Copilot\Tools\ShooterContextTool;
use App\Models\AiReflection;
use App\Models\Session;
use App\Models\SessionWeapon;
use App\Models\User;
use App\Models\Weapon;
use Laravel\Ai\Tools\Request;

function makeShooterContextTool(User $user): ShooterContextTool
{
    return (new ShooterContextTool)
        ->forPanel('admin')
        ->forUser($user)
        ->forTenant(null)
        ->forConversation(null);
}

it('returns a friendly message when the shooter has no data', function () {
    $user = User::factory()->create();

    $output = makeShooterContextTool($user)->handle(new Request);

    expect((string) $output)->toContain('Geen schietgegevens gevonden');
});

it('summarises recent sessions, weapon aggregates and the latest reflection', function () {
    $user = User::factory()->create();
    $weapon = Weapon::factory()->create([
        'user_id' => $user->id,
        'name' => 'CZ Shadow 2',
        'caliber' => '9mm',
    ]);

    $session = Session::factory()->create([
        'user_id' => $user->id,
        'date' => now()->subDays(2)->toDateString(),
        'range_name' => 'KJ Schietbaan',
    ]);

    SessionWeapon::factory()->create([
        'session_id' => $session->id,
        'weapon_id' => $weapon->id,
        'distance_m' => 25,
        'rounds_fired' => 40,
        'group_quality_text' => 'Strakke groep, lichte high-left tendens',
    ]);

    AiReflection::factory()->create([
        'session_id' => $session->id,
        'summary' => 'Goede sessie',
        'positives' => ['Stabiele houding'],
        'improvements' => ['Trekker rustiger'],
        'next_focus' => 'Dry-fire drills',
    ]);

    $output = (string) makeShooterContextTool($user)->handle(new Request);

    expect($output)
        ->toContain('Recente sessies')
        ->toContain('CZ Shadow 2')
        ->toContain('25m')
        ->toContain('40 schoten')
        ->toContain('Per-wapen aggregaties')
        ->toContain('1 sessies, 40 schoten')
        ->toContain('Meest recente AI-reflectie')
        ->toContain('Stabiele houding')
        ->toContain('Dry-fire drills');
});

it('filters by weapon_id when provided', function () {
    $user = User::factory()->create();
    $glock = Weapon::factory()->create(['user_id' => $user->id, 'name' => 'Glock 17']);
    $cz = Weapon::factory()->create(['user_id' => $user->id, 'name' => 'CZ Shadow 2']);

    $glockSession = Session::factory()->create(['user_id' => $user->id, 'date' => now()->subDay()->toDateString()]);
    SessionWeapon::factory()->create([
        'session_id' => $glockSession->id,
        'weapon_id' => $glock->id,
    ]);

    $czSession = Session::factory()->create(['user_id' => $user->id, 'date' => now()->subDays(3)->toDateString()]);
    SessionWeapon::factory()->create([
        'session_id' => $czSession->id,
        'weapon_id' => $cz->id,
    ]);

    $output = (string) makeShooterContextTool($user)->handle(new Request(['weapon_id' => $cz->id]));

    expect($output)
        ->toContain('CZ Shadow 2')
        ->not->toContain('Glock 17');
});

it('only returns data for the active user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherWeapon = Weapon::factory()->create(['user_id' => $other->id, 'name' => 'Other shooter pistol']);
    $otherSession = Session::factory()->create(['user_id' => $other->id]);
    SessionWeapon::factory()->create([
        'session_id' => $otherSession->id,
        'weapon_id' => $otherWeapon->id,
    ]);

    $output = (string) makeShooterContextTool($user)->handle(new Request);

    expect($output)
        ->toContain('Geen schietgegevens gevonden')
        ->not->toContain('Other shooter pistol');
});

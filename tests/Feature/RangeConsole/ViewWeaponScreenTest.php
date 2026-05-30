<?php

declare(strict_types=1);

use App\Filament\Resources\WeaponResource\Pages\ViewWeapon;
use App\Models\AiReflection;
use App\Models\Session;
use App\Models\SessionShot;
use App\Models\SessionWeapon;
use App\Models\User;
use App\Models\Weapon;
use Livewire\Livewire;

function weaponShots(Session $session, int $count, int $ring = 10, int $score = 10): void
{
    foreach (range(0, $count - 1) as $i) {
        SessionShot::factory()->for($session)->create([
            'turn_index' => intdiv($i, 10),
            'shot_index' => $i % 10,
            'ring' => $ring,
            'score' => $score,
        ]);
    }
}

it('renders weapon-detail ID card, KPIs and trend headers for a fresh weapon', function (): void {
    $user = User::factory()->create();
    $weapon = Weapon::factory()->for($user)->create([
        'name' => 'Walther LP500',
        'caliber' => '4.5 mm',
        'serial_number' => 'LP500-4421',
    ]);

    Livewire::actingAs($user)
        ->test(ViewWeapon::class, ['record' => $weapon->id])
        ->assertOk()
        ->assertSee('Walther LP500')
        ->assertSee('LP500-4421')
        ->assertSee('KALIBRATIE')
        ->assertSee('KORREL')
        ->assertSee('Sessies')
        ->assertSee('Schoten totaal')
        ->assertSee('Gem. score')
        ->assertSee('Score-trend')
        ->assertSee('Sessies met dit wapen')
        ->assertSee('Nog geen sessies met dit wapen');
});

it('shows the kalibratie values when present, dashes when null', function (): void {
    $user = User::factory()->create();
    $weapon = Weapon::factory()->for($user)->create([
        'korrel_correction' => '+2',
        'vizier_correction' => '−1 R',
        'trigger_weight_g' => 520,
        'grip_size' => 'M · v2',
    ]);

    Livewire::actingAs($user)
        ->test(ViewWeapon::class, ['record' => $weapon->id])
        ->assertSee('+2')
        ->assertSee('−1 R')
        ->assertSee('520 g')
        ->assertSee('M · v2');

    $empty = Weapon::factory()->for($user)->create([
        'korrel_correction' => null,
        'vizier_correction' => null,
        'trigger_weight_g' => null,
        'grip_size' => null,
    ]);

    Livewire::actingAs($user)
        ->test(ViewWeapon::class, ['record' => $empty->id])
        ->assertSee('—');
});

it('renders KPIs and the sessies-tabel when the weapon has sessions', function (): void {
    $user = User::factory()->create();
    $weapon = Weapon::factory()->for($user)->create();

    foreach (range(1, 3) as $i) {
        $session = Session::factory()->for($user)->create([
            'date' => now()->subDays($i * 2),
            'range_name' => 'SV Diemen',
        ]);
        SessionWeapon::factory()->for($session)->for($weapon)->create(['rounds_fired' => 10]);
        weaponShots($session, 10, 10, 10);
    }

    $latest = Session::query()->where('user_id', $user->id)->orderByDesc('date')->first();
    AiReflection::factory()->for($latest)->create();

    Livewire::actingAs($user)
        ->test(ViewWeapon::class, ['record' => $weapon->id])
        ->assertSee('SV Diemen')
        ->assertSee('AI')
        ->assertSeeText('open');
});

it('refuses to render a weapon owned by another user', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $foreign = Weapon::factory()->for($other)->create();

    Livewire::actingAs($user)
        ->test(ViewWeapon::class, ['record' => $foreign->id]);
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

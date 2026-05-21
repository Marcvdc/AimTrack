<?php

declare(strict_types=1);

use App\Enums\WeaponType;
use App\Filament\Resources\WeaponResource;
use App\Filament\Resources\WeaponResource\Pages\CreateWeapon;
use App\Filament\Resources\WeaponResource\Pages\ListWeapons;
use App\Models\AmmoType;
use App\Models\User;
use App\Models\Weapon;
use Livewire\Livewire;

test('weapons list renders empty state when user has no weapons', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(WeaponResource::getUrl('index'));

    $response->assertOk();
    $response->assertSee('data-testid="weapons-empty-state"', escape: false);
    $response->assertSee('Voeg je eerste wapen toe', escape: false);
    $response->assertSee('Elk wapen krijgt zijn eigen overzicht', escape: false);
});

test('weapons empty state renders three starter template cards', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(WeaponResource::getUrl('index'));

    $response->assertSee('data-testid="weapons-template-luchtpistool"', escape: false);
    $response->assertSee('data-testid="weapons-template-pistool-9mm"', escape: false);
    $response->assertSee('data-testid="weapons-template-vrij-pistool"', escape: false);
    $response->assertSee('Luchtpistool', escape: false);
    $response->assertSee('Vrij pistool', escape: false);
    $response->assertSee('4.5 mm', escape: false);
    $response->assertSee('9×19 mm', escape: false);
    $response->assertSee('.22 LR', escape: false);
});

test('luchtpistool template card has the accent "meest gebruikt" chip', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(WeaponResource::getUrl('index'));

    $response->assertSee('● MEEST GEBRUIKT', escape: false);
});

test('weapons empty state exposes both CTAs and links to weapon-create', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(WeaponResource::getUrl('index'));

    $response->assertSee('Voeg wapen toe', escape: false);
    $response->assertSee(WeaponResource::getUrl('create'), escape: false);
    $response->assertSee('Demo-data inladen', escape: false);
    $response->assertSee('wire:click="seedDemoData"', escape: false);
});

test('weapons list hides empty state when user has at least one weapon', function (): void {
    $user = User::factory()->create();
    Weapon::factory()->for($user)->create();

    $this->actingAs($user);

    $response = $this->get(WeaponResource::getUrl('index'));

    $response->assertOk();
    $response->assertDontSee('data-testid="weapons-empty-state"', escape: false);
});

test('weapons empty state is scoped per user', function (): void {
    $otherUser = User::factory()->create();
    Weapon::factory()->for($otherUser)->create();

    $me = User::factory()->create();
    $this->actingAs($me);

    $response = $this->get(WeaponResource::getUrl('index'));

    $response->assertSee('data-testid="weapons-empty-state"', escape: false);
});

test('CreateWeapon with luchtpistool template pre-fills name, weapon_type and caliber', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::withQueryParams(['template' => 'luchtpistool'])
        ->test(CreateWeapon::class)
        ->assertFormSet([
            'name' => 'Luchtpistool',
            'weapon_type' => WeaponType::PISTOL->value,
            'caliber' => '4.5 mm',
        ]);
});

test('CreateWeapon with vrij-pistool template pre-fills .22 LR caliber', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::withQueryParams(['template' => 'vrij-pistool'])
        ->test(CreateWeapon::class)
        ->assertFormSet([
            'name' => 'Vrij pistool',
            'caliber' => '.22 LR',
        ]);
});

test('CreateWeapon without template uses default form values', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(CreateWeapon::class)
        ->assertFormSet([
            'name' => null,
            'caliber' => null,
        ]);
});

test('CreateWeapon with unknown template key falls back to default form', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::withQueryParams(['template' => 'bazooka'])
        ->test(CreateWeapon::class)
        ->assertFormSet([
            'name' => null,
        ]);
});

test('CreateWeapon ensures matching AmmoType row exists after template prefill', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect(AmmoType::query()->where('user_id', $user->id)->where('caliber', '4.5 mm')->exists())->toBeFalse();

    Livewire::withQueryParams(['template' => 'luchtpistool'])
        ->test(CreateWeapon::class);

    expect(AmmoType::query()->where('user_id', $user->id)->where('caliber', '4.5 mm')->exists())->toBeTrue();
});

test('seedDemoData placeholder on ListWeapons emits an info notification', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(ListWeapons::class)
        ->call('seedDemoData')
        ->assertDispatched('notificationsSent');
});

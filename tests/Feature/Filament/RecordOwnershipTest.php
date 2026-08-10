<?php

use App\Filament\Resources\AmmoTypeResource\Pages\CreateAmmoType;
use App\Filament\Resources\LocationResource\Pages\CreateLocation;
use App\Filament\Resources\SessionResource\Pages\CreateSession;
use App\Filament\Resources\SessionResource\Pages\EditSession;
use App\Filament\Resources\WeaponResource\Pages\CreateWeapon;
use App\Filament\Resources\WeaponResource\Pages\EditWeapon;
use App\Models\AmmoType;
use App\Models\Location;
use App\Models\Session;
use App\Models\User;
use App\Models\Weapon;
use Livewire\Livewire;

/*
 * Regressietests bij GHSA-w8rm-7p6x-jrrr.
 *
 * user_id kwam uit een Hidden-veld met ->default(auth()->id()). Een default is
 * geen dwang: een aangepaste request kon records op naam van een ander account
 * wegschrijven (aanmaken) of een bestaand record naar een ander account
 * doorschuiven (bewerken).
 */

beforeEach(function () {
    $this->ik = User::factory()->create();
    $this->slachtoffer = User::factory()->create();

    $this->actingAs($this->ik);
});

it('negeert een meegestuurd user_id bij het aanmaken van een sessie', function () {
    Livewire::test(CreateSession::class)
        ->fillForm([
            'user_id' => $this->slachtoffer->id,
            'date' => now()->subDay()->format('Y-m-d'),
            'sessionWeapons' => [],
            'attachments' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $session = Session::query()->latest('id')->first();

    expect($session->user_id)->toBe($this->ik->id)
        ->and(Session::query()->where('user_id', $this->slachtoffer->id)->count())->toBe(0);
});

it('schuift een sessie niet door naar een ander account bij het bewerken', function () {
    $session = Session::factory()->create([
        'user_id' => $this->ik->id,
        'date' => now()->subDay(),
    ]);

    Livewire::test(EditSession::class, ['record' => $session->getKey()])
        ->fillForm(['user_id' => $this->slachtoffer->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($session->refresh()->user_id)->toBe($this->ik->id);
});

it('weigert een sessiedatum in de toekomst', function () {
    Livewire::test(CreateSession::class)
        ->fillForm([
            'date' => now()->addYears(4)->format('Y-m-d'),
            'sessionWeapons' => [],
            'attachments' => [],
        ])
        ->call('create')
        ->assertHasFormErrors(['date']);
});

it('negeert een meegestuurd user_id bij het aanmaken van een wapen', function () {
    Livewire::test(CreateWeapon::class)
        ->fillForm([
            'user_id' => $this->slachtoffer->id,
            'name' => 'Testwapen',
            'weapon_type' => 'pistool',
            'caliber' => '9×19 mm',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Weapon::query()->latest('id')->first()->user_id)->toBe($this->ik->id);
});

it('schuift een wapen niet door naar een ander account bij het bewerken', function () {
    $weapon = Weapon::factory()->create([
        'user_id' => $this->ik->id,
        'caliber' => '9×19 mm',
    ]);

    Livewire::test(EditWeapon::class, ['record' => $weapon->getKey()])
        ->fillForm(['user_id' => $this->slachtoffer->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($weapon->refresh()->user_id)->toBe($this->ik->id);
});

it('negeert een meegestuurd user_id bij het aanmaken van een locatie', function () {
    Livewire::test(CreateLocation::class)
        ->fillForm([
            'user_id' => $this->slachtoffer->id,
            'name' => 'Testbaan',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Location::query()->latest('id')->first()->user_id)->toBe($this->ik->id);
});

it('negeert een meegestuurd user_id bij het aanmaken van een munitietype', function () {
    Livewire::test(CreateAmmoType::class)
        ->fillForm([
            'user_id' => $this->slachtoffer->id,
            'name' => 'Testmunitie',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(AmmoType::query()->latest('id')->first()->user_id)->toBe($this->ik->id);
});

it('biedt user_id niet langer als formulierveld aan', function () {
    $schemas = [
        App\Filament\Resources\WeaponResource::class,
        App\Filament\Resources\LocationResource::class,
        App\Filament\Resources\AmmoTypeResource::class,
    ];

    foreach ($schemas as $resource) {
        expect(file_get_contents((new ReflectionClass($resource))->getFileName()))
            ->not->toContain("Hidden::make('user_id')");
    }
});

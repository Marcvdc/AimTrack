<?php

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('is niet zichtbaar voor een niet-admin', function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => false]));

    expect(UserResource::canViewAny())->toBeFalse()
        ->and(UserResource::shouldRegisterNavigation())->toBeFalse();
});

it('is zichtbaar voor een app-beheerder', function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    expect(UserResource::canViewAny())->toBeTrue();
});

it('promoveert een gebruiker tot beheerder via de actie', function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $doel = User::factory()->create(['is_admin' => false]);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('toggleAdmin')->table($doel))
        ->assertNotified();

    expect($doel->fresh()->is_admin)->toBeTrue();
});

it('trekt beheerderrechten in zolang er nog een andere beheerder is', function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $andere = User::factory()->create(['is_admin' => true]);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('toggleAdmin')->table($andere));

    expect($andere->fresh()->is_admin)->toBeFalse();
});

it('weigert het intrekken van de laatste beheerder', function (): void {
    $enige = User::factory()->create(['is_admin' => true]);
    $this->actingAs($enige);

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('toggleAdmin')->table($enige));

    expect($enige->fresh()->is_admin)->toBeTrue();
});

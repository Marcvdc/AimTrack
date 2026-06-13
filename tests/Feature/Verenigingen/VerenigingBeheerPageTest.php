<?php

use App\Enums\VerenigingRol;
use App\Filament\Pages\VerenigingBeheer;
use App\Models\User;
use App\Models\Vereniging;
use Livewire\Livewire;

function beheerderVan(Vereniging $vereniging): User
{
    $admin = User::factory()->create(['active_vereniging_id' => $vereniging->id]);
    $vereniging->members()->attach($admin, ['role' => VerenigingRol::Admin->value]);

    return $admin;
}

it('is niet toegankelijk voor een gewoon lid', function (): void {
    $vereniging = Vereniging::factory()->create();
    $lid = User::factory()->create(['active_vereniging_id' => $vereniging->id]);
    $vereniging->members()->attach($lid, ['role' => VerenigingRol::Member->value]);
    $this->actingAs($lid);

    expect(VerenigingBeheer::canAccess())->toBeFalse();
});

it('is toegankelijk voor een beheerder', function (): void {
    $vereniging = Vereniging::factory()->create();
    $this->actingAs(beheerderVan($vereniging));

    expect(VerenigingBeheer::canAccess())->toBeTrue();

    Livewire::test(VerenigingBeheer::class)->assertOk();
});

it('laat een beheerder een bestaand lid toevoegen', function (): void {
    $vereniging = Vereniging::factory()->create();
    $this->actingAs(beheerderVan($vereniging));
    $nieuw = User::factory()->create(['email' => 'nieuw@example.test']);

    Livewire::test(VerenigingBeheer::class)
        ->set('data.email', 'nieuw@example.test')
        ->set('data.role', VerenigingRol::Coach->value)
        ->call('voegLidToe')
        ->assertNotified();

    expect($nieuw->fresh()->rolInVereniging($vereniging))->toBe(VerenigingRol::Coach);
});

it('toont een nette fout bij een onbekend e-mailadres', function (): void {
    $vereniging = Vereniging::factory()->create();
    $this->actingAs(beheerderVan($vereniging));

    Livewire::test(VerenigingBeheer::class)
        ->set('data.email', 'onbekend@example.test')
        ->set('data.role', VerenigingRol::Member->value)
        ->call('voegLidToe')
        ->assertNotified();

    expect($vereniging->members()->count())->toBe(1);
});

it('verwijdert een lid via de record-actie', function (): void {
    $vereniging = Vereniging::factory()->create();
    $this->actingAs(beheerderVan($vereniging));
    $lid = User::factory()->create();
    $vereniging->members()->attach($lid, ['role' => VerenigingRol::Member->value]);

    Livewire::test(VerenigingBeheer::class)
        ->callAction(\Filament\Actions\Testing\TestAction::make('verwijderLid')->table($lid));

    expect($vereniging->members()->whereKey($lid->id)->exists())->toBeFalse();
});

it('slaat de gedeelde key op via het formulier', function (): void {
    $vereniging = Vereniging::factory()->create();
    $this->actingAs(beheerderVan($vereniging));

    Livewire::test(VerenigingBeheer::class)
        ->set('data.anthropic_api_key', 'sk-ant-gedeeld')
        ->call('save')
        ->assertNotified();

    expect($vereniging->fresh()->anthropic_api_key)->toBe('sk-ant-gedeeld');
});

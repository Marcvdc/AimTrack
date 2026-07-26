<?php

use App\Enums\VerenigingRol;
use App\Filament\Pages\VerenigingAanmaken;
use App\Models\User;
use App\Models\Vereniging;
use Livewire\Livewire;

it('is niet toegankelijk voor een niet-admin', function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => false]));

    expect(VerenigingAanmaken::canAccess())->toBeFalse();
});

it('is toegankelijk voor een app-beheerder', function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    expect(VerenigingAanmaken::canAccess())->toBeTrue();
});

it('maakt een vereniging aan en wijst de gekozen beheerder toe', function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $beheerder = User::factory()->create();

    Livewire::test(VerenigingAanmaken::class)
        ->fillForm([
            'naam' => 'Nieuwe Club',
            'beheerder_id' => $beheerder->id,
        ])
        ->call('aanmaken')
        ->assertNotified();

    $vereniging = Vereniging::query()->where('naam', 'Nieuwe Club')->first();

    expect($vereniging)->not->toBeNull()
        ->and($beheerder->fresh()->active_vereniging_id)->toBe($vereniging->id)
        ->and($beheerder->fresh()->rolInVereniging($vereniging))->toBe(VerenigingRol::Admin);
});

it('valideert dat naam en beheerder verplicht zijn', function (): void {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(VerenigingAanmaken::class)
        ->fillForm(['naam' => null, 'beheerder_id' => null])
        ->call('aanmaken')
        ->assertHasFormErrors(['naam', 'beheerder_id']);

    expect(Vereniging::query()->count())->toBe(0);
});

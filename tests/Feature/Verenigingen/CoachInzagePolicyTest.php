<?php

use App\Enums\VerenigingRol;
use App\Models\Session;
use App\Models\User;
use App\Models\Vereniging;
use App\Models\Weapon;

function lidMet(Vereniging $vereniging, VerenigingRol $rol): User
{
    $user = User::factory()->create(['active_vereniging_id' => $vereniging->id]);
    $vereniging->members()->attach($user, ['role' => $rol->value]);

    return $user;
}

it('laat een eigenaar zijn eigen sessie zien en bewerken', function (): void {
    $user = User::factory()->create();
    $session = Session::factory()->for($user)->create();

    expect($user->can('view', $session))->toBeTrue()
        ->and($user->can('update', $session))->toBeTrue()
        ->and($user->can('delete', $session))->toBeTrue();
});

it('laat een coach sessies van leden in dezelfde vereniging read-only zien', function (): void {
    $vereniging = Vereniging::factory()->create();
    $coach = lidMet($vereniging, VerenigingRol::Coach);
    $lid = lidMet($vereniging, VerenigingRol::Member);
    $session = Session::factory()->for($lid)->create();

    expect($coach->can('view', $session))->toBeTrue()
        ->and($coach->can('update', $session))->toBeFalse()
        ->and($coach->can('delete', $session))->toBeFalse();
});

it('blokkeert coach-inzage over verenigingen heen', function (): void {
    $clubA = Vereniging::factory()->create();
    $clubB = Vereniging::factory()->create();
    $coach = lidMet($clubA, VerenigingRol::Coach);
    $vreemdLid = lidMet($clubB, VerenigingRol::Member);
    $session = Session::factory()->for($vreemdLid)->create();

    expect($coach->can('view', $session))->toBeFalse();
});

it('blokkeert dat een member peer-data ziet', function (): void {
    $vereniging = Vereniging::factory()->create();
    $lidA = lidMet($vereniging, VerenigingRol::Member);
    $lidB = lidMet($vereniging, VerenigingRol::Member);
    $session = Session::factory()->for($lidB)->create();

    expect($lidA->can('view', $session))->toBeFalse();
});

it('past dezelfde coach-inzage toe op wapens', function (): void {
    $vereniging = Vereniging::factory()->create();
    $coach = lidMet($vereniging, VerenigingRol::Coach);
    $lid = lidMet($vereniging, VerenigingRol::Member);
    $weapon = Weapon::factory()->for($lid)->create();

    expect($coach->can('view', $weapon))->toBeTrue()
        ->and($coach->can('update', $weapon))->toBeFalse();
});

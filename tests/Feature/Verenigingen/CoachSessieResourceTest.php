<?php

use App\Enums\VerenigingRol;
use App\Filament\Resources\CoachSessies\CoachSessieResource;
use App\Filament\Resources\CoachSessies\Pages\ListCoachSessies;
use App\Models\Session;
use App\Models\User;
use App\Models\Vereniging;
use Livewire\Livewire;

function coachLid(Vereniging $vereniging, VerenigingRol $rol): User
{
    $user = User::factory()->create(['active_vereniging_id' => $vereniging->id]);
    $vereniging->members()->attach($user, ['role' => $rol->value]);

    return $user;
}

it('toont de resource niet aan een gewoon lid', function (): void {
    $vereniging = Vereniging::factory()->create();
    $lid = coachLid($vereniging, VerenigingRol::Member);
    $this->actingAs($lid);

    expect(CoachSessieResource::canViewAny())->toBeFalse();
});

it('toont de resource aan een coach', function (): void {
    $vereniging = Vereniging::factory()->create();
    $coach = coachLid($vereniging, VerenigingRol::Coach);
    $this->actingAs($coach);

    expect(CoachSessieResource::canViewAny())->toBeTrue();
});

it('laat een coach alleen sessies van leden in de eigen vereniging zien', function (): void {
    $vereniging = Vereniging::factory()->create();
    $andereClub = Vereniging::factory()->create();

    $coach = coachLid($vereniging, VerenigingRol::Coach);
    $lid = coachLid($vereniging, VerenigingRol::Member);
    $vreemdLid = coachLid($andereClub, VerenigingRol::Member);

    $eigenSessie = Session::factory()->for($lid)->create();
    $vreemdeSessie = Session::factory()->for($vreemdLid)->create();

    $this->actingAs($coach);

    Livewire::test(ListCoachSessies::class)
        ->assertCanSeeTableRecords([$eigenSessie])
        ->assertCanNotSeeTableRecords([$vreemdeSessie]);
});

it('biedt geen aanmaak- of bewerkactie op de coach-resource', function (): void {
    expect(CoachSessieResource::canCreate())->toBeFalse()
        ->and(CoachSessieResource::canEdit(Session::factory()->make()))->toBeFalse()
        ->and(CoachSessieResource::canDelete(Session::factory()->make()))->toBeFalse();
});

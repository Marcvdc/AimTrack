<?php

declare(strict_types=1);

use App\Enums\VerenigingRol;
use App\Livewire\SessionShotBoard;
use App\Models\Session;
use App\Models\User;
use App\Models\Vereniging;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Een coach die inzage heeft in de sessie van een lid, maar geen bewerkrecht.
 *
 * @return array{0: User, 1: Session}
 */
function coachEnSessieVanLid(): array
{
    $vereniging = Vereniging::factory()->create();

    $coach = User::factory()->create(['active_vereniging_id' => $vereniging->id]);
    $vereniging->members()->attach($coach, ['role' => VerenigingRol::Coach->value]);

    $lid = User::factory()->create(['active_vereniging_id' => $vereniging->id]);
    $vereniging->members()->attach($lid, ['role' => VerenigingRol::Member->value]);

    return [$coach, Session::factory()->for($lid)->create()];
}

test('een meekijker krijgt het bord alleen-lezen', function (): void {
    [$coach, $session] = coachEnSessieVanLid();

    expect($coach->can('view', $session))->toBeTrue()
        ->and($coach->can('update', $session))->toBeFalse();

    Livewire::actingAs($coach)
        ->test(SessionShotBoard::class, ['session' => $session])
        ->assertSet('canEdit', false);
});

test('een meekijker kan het bewerkrecht niet zelf aanzetten', function (): void {
    /*
     * canEdit stuurt elke muterende actie op het bord aan. Staat de property niet
     * op slot, dan zet een meekijker hem vanuit de browser op true en kan hij
     * schoten verplaatsen, beurten bevestigen en foto's uploaden. Dat laatste doet
     * een betaalde vision-call op de sleutel van de schutter.
     */
    [$coach, $session] = coachEnSessieVanLid();

    Livewire::actingAs($coach)
        ->test(SessionShotBoard::class, ['session' => $session])
        ->set('canEdit', true);
})->throws(CannotUpdateLockedPropertyException::class);

test('het bewerkrecht wordt per actie getoetst en niet alleen bij het openen', function (): void {
    /*
     * Het slot op canEdit dekt het manipuleren vanuit de browser af, maar niet een
     * recht dat vervalt terwijl het bord openstaat. De component houdt zijn state
     * tussen requests vast, dus zonder toets in de actie zelf blijft de vorige
     * uitkomst gelden.
     */
    $eigenaar = User::factory()->create();
    $session = Session::factory()->for($eigenaar)->create();
    $shot = app(App\Services\Sessions\SessionShotService::class)
        ->recordShot($session, 0, 0.5, 0.5);

    $component = Livewire::actingAs($eigenaar)
        ->test(SessionShotBoard::class, ['session' => $session])
        ->assertSet('canEdit', true);

    $session->forceFill(['user_id' => User::factory()->create()->id])->save();

    $component->call('moveShot', $shot->id, 0.9, 0.9);

    expect(round((float) $shot->fresh()->x_normalized, 3))->toBe(0.5);
});

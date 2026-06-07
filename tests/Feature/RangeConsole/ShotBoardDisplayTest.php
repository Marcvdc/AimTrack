<?php

declare(strict_types=1);

use App\Livewire\SessionShotBoard;
use App\Models\Session;
use App\Models\SessionShot;
use App\Models\User;
use Livewire\Livewire;

/**
 * Schot op afstand 0.13 van het midden → decimaalscore 7.4, hele ring 8.
 * Het verschil maakt zichtbaar of decimaal-notatie aan of uit staat.
 */
function offCentreShot(Session $session): SessionShot
{
    return SessionShot::factory()->create([
        'session_id' => $session->id,
        'turn_index' => 0,
        'shot_index' => 0,
        'x_normalized' => 0.5,
        'y_normalized' => 0.37,
        'distance_from_center' => 0.13,
        'ring' => 8,
        'score' => 8,
    ]);
}

it('records a shot on the current turn and scores it', function (): void {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(SessionShotBoard::class, ['session' => $session])
        ->assertSet('currentTurnIndex', 0)
        ->call('recordShot', 0.5, 0.5);

    $shot = $session->shots()->first();

    expect($shot)->not->toBeNull()
        ->and($shot->turn_index)->toBe(0)
        ->and($shot->ring)->toBe(10)
        ->and($shot->score)->toBe(10);
});

it('summarises the score column as a decimal total/average when decimal notation is on', function (): void {
    $user = User::factory()->create();
    $user->setPreference('decimal_notation', true);
    $session = Session::factory()->create(['user_id' => $user->id]);

    $board = Livewire::actingAs($user)
        ->test(SessionShotBoard::class, ['session' => $session])
        ->instance();

    // distance 0.13 -> 7.4 ; distance 0.0 -> 10.0
    $shots = collect([
        (object) ['distance_from_center' => 0.13, 'score' => 8],
        (object) ['distance_from_center' => 0.0, 'score' => 10],
    ]);

    expect($board->summarizeScores($shots, false))->toBe('17.4')
        ->and($board->summarizeScores($shots, true))->toBe('8.7');
});

it('summarises the score column as whole rings when decimal notation is off', function (): void {
    $user = User::factory()->create();
    $user->setPreference('decimal_notation', false);
    $session = Session::factory()->create(['user_id' => $user->id]);

    $board = Livewire::actingAs($user)
        ->test(SessionShotBoard::class, ['session' => $session])
        ->instance();

    $shots = collect([
        (object) ['distance_from_center' => 0.13, 'score' => 8],
        (object) ['distance_from_center' => 0.0, 'score' => 10],
    ]);

    expect($board->summarizeScores($shots, false))->toBe('18')
        ->and($board->summarizeScores($shots, true))->toBe('9.0');
});

it('initialises display flags from the user preferences', function (): void {
    $user = User::factory()->create();
    $user->setPreference('board_show_rings', true);
    $user->setPreference('decimal_notation', false);
    $session = Session::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(SessionShotBoard::class, ['session' => $session])
        ->assertSet('showRings', true)
        ->assertSet('decimalNotation', false);
});

it('renders a ring-toggle control bound to toggleRings', function (): void {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(SessionShotBoard::class, ['session' => $session])
        ->assertSeeHtml('wire:click="toggleRings"');
});

it('toggles ring display and persists it to the user', function (): void {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(SessionShotBoard::class, ['session' => $session])
        ->assertSet('showRings', false)
        ->call('toggleRings')
        ->assertSet('showRings', true);

    expect($user->fresh()->preference('board_show_rings'))->toBeTrue();
});

it('formats a score with one decimal when decimal notation is on', function (): void {
    $user = User::factory()->create();
    $user->setPreference('decimal_notation', true);
    $session = Session::factory()->create(['user_id' => $user->id]);
    $shot = offCentreShot($session);

    $board = Livewire::actingAs($user)
        ->test(SessionShotBoard::class, ['session' => $session])
        ->instance();

    expect($board->formatScore($shot->score, $shot))->toBe('+7.4');
});

it('formats a score as a whole ring when decimal notation is off', function (): void {
    $user = User::factory()->create();
    $user->setPreference('decimal_notation', false);
    $session = Session::factory()->create(['user_id' => $user->id]);
    $shot = offCentreShot($session);

    $board = Livewire::actingAs($user)
        ->test(SessionShotBoard::class, ['session' => $session])
        ->instance();

    expect($board->formatScore($shot->score, $shot))->toBe('+8');
});

it('formats a zero-ring shot as +0 when decimal notation is off', function (): void {
    $user = User::factory()->create();
    $user->setPreference('decimal_notation', false);
    $session = Session::factory()->create(['user_id' => $user->id]);
    $shot = SessionShot::factory()->create([
        'session_id' => $session->id,
        'turn_index' => 0,
        'shot_index' => 0,
        'x_normalized' => 0.0,
        'y_normalized' => 0.0,
        'distance_from_center' => 0.6,
        'ring' => 0,
        'score' => 0,
    ]);

    $board = Livewire::actingAs($user)
        ->test(SessionShotBoard::class, ['session' => $session])
        ->instance();

    expect($board->formatScore($shot->score, $shot))->toBe('+0');
});

it('does not record a shot when the board is not editable', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $owner->id]);

    Livewire::actingAs($viewer)
        ->test(SessionShotBoard::class, ['session' => $session])
        ->assertSet('canEdit', false)
        ->call('recordShot', 0.5, 0.5);

    expect($session->shots()->count())->toBe(0);
});

it('initialises display flags from the viewer preferences on a read-only board', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $viewer->setPreference('board_show_rings', true);
    $session = Session::factory()->create(['user_id' => $owner->id]);

    Livewire::actingAs($viewer)
        ->test(SessionShotBoard::class, ['session' => $session, 'readOnly' => true])
        ->assertSet('canEdit', false)
        ->assertSet('showRings', true);
});

it('reflects an enabled ring preference in the toggle aria-checked state', function (): void {
    $user = User::factory()->create();
    $user->setPreference('board_show_rings', true);
    $session = Session::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(SessionShotBoard::class, ['session' => $session])
        ->assertSeeHtml('aria-checked="true"');
});

it('shows the ring toggle off by default', function (): void {
    $user = User::factory()->create();
    $session = Session::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(SessionShotBoard::class, ['session' => $session])
        ->assertSeeHtml('aria-checked="false"');
});

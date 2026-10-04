<?php

declare(strict_types=1);

use App\Livewire\SessionShotBoard;
use App\Models\Session;
use App\Models\SessionShot;
use App\Models\User;
use App\Services\Sessions\SessionShotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function correctionSession(): Session
{
    $user = User::factory()->create();
    test()->actingAs($user);

    return Session::factory()->create(['user_id' => $user->id]);
}

function photoShot(Session $session, float $x = 0.6, float $y = 0.6, string $source = 'photo'): SessionShot
{
    return $session->shots()->create([
        'turn_index' => 0,
        'shot_index' => 0,
        'source' => $source,
        'x_normalized' => $x,
        'y_normalized' => $y,
        'distance_from_center' => 0.14,
        'ring' => 8,
        'score' => 8,
        'metadata' => ['confidence' => 0.55, 'read_ring' => 8],
    ]);
}

test('een schot verslepen verplaatst het en herberekent de score', function (): void {
    $session = correctionSession();
    $shot = photoShot($session, 0.8, 0.8);

    app(SessionShotService::class)->moveShot($shot, 0.5, 0.5);

    expect((float) $shot->x_normalized)->toBe(0.5)
        ->and((float) $shot->y_normalized)->toBe(0.5)
        ->and($shot->ring)->toBe(10)
        ->and($shot->score)->toBe(10);
});

test('een gecorrigeerd fotoschot krijgt een eigen bron', function (): void {
    $session = correctionSession();
    $shot = photoShot($session);

    app(SessionShotService::class)->moveShot($shot, 0.5, 0.5);

    expect($shot->source)->toBe(SessionShotService::SOURCE_PHOTO_CORRECTED);
});

test('de oorspronkelijke positie blijft bewaard als grondwaarheid', function (): void {
    $session = correctionSession();
    $shot = photoShot($session, 0.62, 0.71);

    app(SessionShotService::class)->moveShot($shot, 0.5, 0.5);

    expect($shot->metadata['corrected_from'])->toBe(['x' => 0.62, 'y' => 0.71, 'ring' => 8])
        ->and($shot->metadata['read_ring'])->toBe(8);
});

test('een handmatig schot verslepen laat de bron met rust', function (): void {
    $session = correctionSession();
    $shot = photoShot($session, 0.6, 0.6, 'manual');

    app(SessionShotService::class)->moveShot($shot, 0.4, 0.4);

    expect($shot->source)->toBe(SessionShotService::SOURCE_MANUAL)
        ->and($shot->metadata)->not->toHaveKey('corrected_from');
});

test('een tweede correctie overschrijft de eerste meting niet', function (): void {
    $session = correctionSession();
    $shot = photoShot($session, 0.9, 0.9);
    $service = app(SessionShotService::class);

    $service->moveShot($shot, 0.6, 0.6);
    $service->moveShot($shot, 0.5, 0.5);

    // De eerste correctie maakte de bron al photo_corrected, dus de tweede voegt
    // geen nieuw ijkpunt toe: het oorspronkelijke modelantwoord blijft staan.
    expect($shot->metadata['corrected_from'])->toBe(['x' => 0.9, 'y' => 0.9, 'ring' => 8]);
});

test('een positie buiten het bord wordt teruggebracht naar de rand', function (): void {
    $session = correctionSession();
    $shot = photoShot($session);

    app(SessionShotService::class)->moveShot($shot, 1.8, -0.4);

    expect((float) $shot->x_normalized)->toBe(1.0)
        ->and((float) $shot->y_normalized)->toBe(0.0);
});

test('het bord verplaatst een schot via de component', function (): void {
    $session = correctionSession();
    $shot = photoShot($session, 0.8, 0.2);

    Livewire::test(SessionShotBoard::class, ['session' => $session])
        ->call('moveShot', $shot->id, 0.5, 0.5);

    expect((float) $shot->fresh()->x_normalized)->toBe(0.5)
        ->and($shot->fresh()->source)->toBe('photo_corrected');
});

test('een meekijker zonder rechten kan niets verslepen', function (): void {
    $session = correctionSession();
    $shot = photoShot($session, 0.8, 0.2);

    Livewire::test(SessionShotBoard::class, ['session' => $session, 'readOnly' => true])
        ->call('moveShot', $shot->id, 0.5, 0.5);

    expect((float) $shot->fresh()->x_normalized)->toBe(0.8);
});

test('een schot uit een andere sessie is niet te verslepen', function (): void {
    $session = correctionSession();
    $vreemd = Session::factory()->create(['user_id' => User::factory()->create()->id]);
    $shot = photoShot($vreemd, 0.8, 0.2);

    Livewire::test(SessionShotBoard::class, ['session' => $session])
        ->call('moveShot', $shot->id, 0.5, 0.5);

    expect((float) $shot->fresh()->x_normalized)->toBe(0.8);
});

test('het bord vertelt dat je een marker kunt verslepen', function (): void {
    Livewire::test(SessionShotBoard::class, ['session' => correctionSession()])
        ->assertSee('SLEEP EEN MARKER');
});

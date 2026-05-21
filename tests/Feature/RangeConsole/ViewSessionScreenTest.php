<?php

declare(strict_types=1);

use App\Filament\Resources\SessionResource\Pages\ViewSession;
use App\Models\AiReflection;
use App\Models\Session;
use App\Models\SessionShot;
use App\Models\User;
use Livewire\Livewire;

function seedShots(Session $session, int $count, int $ring = 10, int $score = 10): void
{
    foreach (range(0, $count - 1) as $i) {
        SessionShot::factory()->for($session)->create([
            'turn_index' => intdiv($i, 10),
            'shot_index' => $i % 10,
            'ring' => $ring,
            'score' => $score,
            'x_normalized' => 0.5,
            'y_normalized' => 0.5,
        ]);
    }
}

it('renders the session-detail layout with header, stats and shot strip', function (): void {
    $user = User::factory()->create();
    $session = Session::factory()->for($user)->create([
        'date' => '2026-05-08',
        'manual_reflection' => 'Nieuwe handgreep gebruikt vanaf serie 3.',
    ]);
    seedShots($session, 20, ring: 10, score: 10);

    Livewire::actingAs($user)
        ->test(ViewSession::class, ['record' => $session->id])
        ->assertOk()
        ->assertSee('SESSIE · S-'.str_pad((string) $session->id, 4, '0', STR_PAD_LEFT))
        ->assertSee('EINDSCORE')
        ->assertSee('Beste schot')
        ->assertSee('Tienen')
        ->assertSee('Negens')
        ->assertSee('Groep')
        ->assertSee('Gem. cadans')
        ->assertSee('Schot-voor-schot')
        ->assertSee('Hit-patroon')
        ->assertSee('Series')
        ->assertSee('Nieuwe handgreep gebruikt');
});

it('falls back to a friendly empty message when the session has no shots', function (): void {
    $user = User::factory()->create();
    $session = Session::factory()->for($user)->create();

    Livewire::actingAs($user)
        ->test(ViewSession::class, ['record' => $session->id])
        ->assertOk()
        ->assertSee('Nog geen schoten gelogd');
});

it('renders the WM-4 OK stamp only when the session has an AI reflection', function (): void {
    $user = User::factory()->create();

    $sessionWithoutRefl = Session::factory()->for($user)->create();
    seedShots($sessionWithoutRefl, 10);

    Livewire::actingAs($user)
        ->test(ViewSession::class, ['record' => $sessionWithoutRefl->id])
        ->assertDontSee('WM-4 OK');

    $sessionWithRefl = Session::factory()->for($user)->create();
    seedShots($sessionWithRefl, 10);
    AiReflection::factory()->for($sessionWithRefl)->create([
        'summary' => 'Sterke openingsserie.',
    ]);

    Livewire::actingAs($user)
        ->test(ViewSession::class, ['record' => $sessionWithRefl->id])
        ->assertSee('WM-4 OK')
        ->assertSee('Sterke openingsserie');
});

it('omits the eigen-notitie panel when the session has no manual reflection', function (): void {
    $user = User::factory()->create();
    $session = Session::factory()->for($user)->create(['manual_reflection' => null]);
    seedShots($session, 5);

    Livewire::actingAs($user)
        ->test(ViewSession::class, ['record' => $session->id])
        ->assertDontSee('Eigen notitie');
});

it('refuses to render sessions owned by another user via the eloquent scope', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $foreignSession = Session::factory()->for($other)->create();

    Livewire::actingAs($user)
        ->test(ViewSession::class, ['record' => $foreignSession->id]);
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

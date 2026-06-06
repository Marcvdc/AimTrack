<?php

declare(strict_types=1);

use App\Filament\Resources\SessionResource\Pages\ListSessions;
use App\Models\AiReflection;
use App\Models\Session;
use App\Models\SessionShot;
use App\Models\User;
use Livewire\Livewire;

it('renders page header and KPI labels for an empty user', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ListSessions::class)
        ->assertOk()
        ->assertSee('Sessies')
        ->assertSee('Sessies / mnd')
        ->assertSee('Schoten totaal')
        ->assertSee('Beste serie')
        ->assertSee('AI-reflecties')
        ->assertSee('Laatste sessie')
        ->assertSee('Trend');
});

it('shows a friendly fallback when user has no sessions yet', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ListSessions::class)
        ->assertSee('Nog geen sessies gelogd');
});

it('renders KPI values from user data', function (): void {
    $user = User::factory()->create();

    $session = Session::factory()->for($user)->create(['date' => now()->startOfMonth()->addDay()]);

    foreach (range(0, 9) as $i) {
        SessionShot::factory()->for($session)->create([
            'turn_index' => 0,
            'shot_index' => $i,
            'ring' => 10,
            'score' => 10,
        ]);
    }

    AiReflection::factory()->for($session)->create([
        'summary' => 'Sterke openingsserie met 5 tienen.',
    ]);

    Livewire::actingAs($user)
        ->test(ListSessions::class)
        ->assertSee('Sterke openingsserie')
        ->assertSee('S-'.str_pad((string) $session->id, 4, '0', STR_PAD_LEFT));
});

it('renders the AI status badge for sessions with a reflection', function (): void {
    $user = User::factory()->create();
    $sessionWithAi = Session::factory()->for($user)->create();
    $sessionWithout = Session::factory()->for($user)->create();
    AiReflection::factory()->for($sessionWithAi)->create();

    Livewire::actingAs($user)
        ->test(ListSessions::class)
        ->assertCanSeeTableRecords([$sessionWithAi, $sessionWithout]);
});

it('does not leak sessions from other users into the KPIs', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Session::factory()->for($other)->count(5)->create(['date' => now()->startOfMonth()->addDay()]);

    Livewire::actingAs($user)
        ->test(ListSessions::class)
        ->assertSee('Sessies / mnd')
        ->assertDontSee('5 in wachtrij');
});

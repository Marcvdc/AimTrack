<?php

declare(strict_types=1);

use App\Filament\Resources\SessionResource;
use App\Filament\Resources\SessionResource\Pages\ListSessions;
use App\Models\Session;
use App\Models\User;
use Livewire\Livewire;

test('sessions list renders empty state when user has no sessions', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(SessionResource::getUrl('index'));

    $response->assertOk();
    $response->assertSee('data-testid="sessions-empty-state"', escape: false);
    $response->assertSee('Nog geen sessies gelogd', escape: false);
    $response->assertSee('Log je eerste training in ongeveer 30 seconden', escape: false);
});

test('sessions empty state exposes both CTAs', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(SessionResource::getUrl('index'));

    $response->assertSee('Eerste sessie loggen', escape: false);
    $response->assertSee(SessionResource::getUrl('create'), escape: false);
    $response->assertSee('Demo-data inladen', escape: false);
    $response->assertSee('wire:click="seedDemoData"', escape: false);
});

test('sessions empty state renders the paper-logbook tip card', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(SessionResource::getUrl('index'));

    $response->assertSee('data-testid="sessions-empty-tip"', escape: false);
    $response->assertSee('💡 TIP', escape: false);
    $response->assertSee('papieren logboek', escape: false);
});

test('sessions list hides empty state when user has at least one session', function (): void {
    $user = User::factory()->create();
    Session::factory()->for($user)->create();

    $this->actingAs($user);

    $response = $this->get(SessionResource::getUrl('index'));

    $response->assertOk();
    $response->assertDontSee('data-testid="sessions-empty-state"', escape: false);
    $response->assertDontSee('Log je eerste training in ongeveer 30 seconden', escape: false);
});

test('sessions empty state is scoped per user — another user with data does not hide my empty state', function (): void {
    $otherUser = User::factory()->create();
    Session::factory()->for($otherUser)->create();

    $me = User::factory()->create();
    $this->actingAs($me);

    $response = $this->get(SessionResource::getUrl('index'));

    $response->assertSee('data-testid="sessions-empty-state"', escape: false);
});

test('seedDemoData placeholder on ListSessions emits an info notification', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(ListSessions::class)
        ->call('seedDemoData')
        ->assertDispatched('notificationsSent');
});

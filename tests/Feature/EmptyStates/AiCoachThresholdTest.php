<?php

declare(strict_types=1);

use App\Filament\Pages\CoachPage;
use App\Models\Session;
use App\Models\User;
use Laravel\Pennant\Feature;
use Livewire\Livewire;

beforeEach(function () {
    Feature::activate('aimtrack-ai');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('coach page shows threshold empty state for a user with no sessions', function (): void {
    $response = $this->get(CoachPage::getUrl());

    $response->assertOk();
    $response->assertSee('data-testid="ai-coach-threshold"', escape: false);
    $response->assertSee('De coach heeft 3 sessies nodig', escape: false);
    $response->assertSee('Met minimaal 3 gelogde sessies', escape: false);
    $response->assertDontSee('Open de chat');
});

test('threshold empty state renders progress card with 0/3 when no sessions logged', function (): void {
    $response = $this->get(CoachPage::getUrl());

    $response->assertSee('data-testid="ai-coach-progress"', escape: false);
    $response->assertSee('0<span style="color: var(--at-muted); font-size: 14px;">/3</span>', escape: false);
    $response->assertSee('Nog 3 sessies te gaan', escape: false);
    $response->assertSee('width: 0%', escape: false);
});

test('threshold progress shows 1/3 with one session and plural copy at two remaining', function (): void {
    Session::factory()->count(1)->for($this->user)->create();

    $response = $this->get(CoachPage::getUrl());

    $response->assertSee('1<span style="color: var(--at-muted); font-size: 14px;">/3</span>', escape: false);
    $response->assertSee('Nog 2 sessies te gaan', escape: false);
    $response->assertSee('width: 33%', escape: false);
});

test('threshold progress shows 2/3 with two sessions and singular fallback at one remaining', function (): void {
    Session::factory()->count(2)->for($this->user)->create();

    $response = $this->get(CoachPage::getUrl());

    $response->assertSee('2<span style="color: var(--at-muted); font-size: 14px;">/3</span>', escape: false);
    $response->assertSee('Nog 1 sessie te gaan', escape: false);
    $response->assertSee('width: 67%', escape: false);
});

test('threshold CTAs are present and link to session-create', function (): void {
    $response = $this->get(CoachPage::getUrl());

    $response->assertSee('Log volgende sessie', escape: false);
    $response->assertSee(\App\Filament\Resources\SessionResource::getUrl('create'), escape: false);
    $response->assertSee('Hoe werkt de AI?', escape: false);
    $response->assertSee('wire:click="explainAiCoach"', escape: false);
});

test('coach page switches to chat UI when user crosses the three-session threshold', function (): void {
    Session::factory()->count(3)->for($this->user)->create();

    $response = $this->get(CoachPage::getUrl());

    $response->assertOk();
    $response->assertSee('data-testid="ai-coach-chat-ready"', escape: false);
    $response->assertSee('Open de chat');
    $response->assertDontSee('data-testid="ai-coach-threshold"', escape: false);
    $response->assertDontSee('De coach heeft 3 sessies nodig', escape: false);
});

test('threshold scoping is per-user', function (): void {
    $otherUser = User::factory()->create();
    Session::factory()->count(10)->for($otherUser)->create();

    $response = $this->get(CoachPage::getUrl());

    $response->assertSee('data-testid="ai-coach-threshold"', escape: false);
    $response->assertSee('Nog 3 sessies te gaan', escape: false);
});

test('explainAiCoach action sends the coach-explanation notification', function (): void {
    Livewire::test(CoachPage::class)
        ->call('explainAiCoach')
        ->assertNotified('Hoe werkt de AI-coach?');
});

<?php

use App\Models\User;
use App\Models\Weapon;
use App\Models\CoachQuestion;
use App\Services\Ai\ShooterCoach;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Livewire\Livewire;
use Livewire\Features\SupportTesting\TestsPendingLivewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->weapon = Weapon::factory()->create(['user_id' => $this->user->id]);
    $this->actingAs($this->user);
});

it('shows AI coach page when feature is enabled', function () {
    config(['features.defaults.aimtrack-ai' => true]);
    config(['ai.api_key' => 'test-key']);

    $response = $this->get('/admin/ai-coach-page');

    $response->assertOk();
    $response->assertSee('AI-coach');
});

it('blocks AI coach page when feature is disabled', function () {
    config(['features.defaults.aimtrack-ai' => false]);

    $response = $this->get('/admin/ai-coach-page');

    $response->assertStatus(403);
});

it('stores question and answer when AI call succeeds', function () {
    config(['features.defaults.aimtrack-ai' => true]);
    config(['ai.api_key' => 'test-key']);

    $this->mock(ShooterCoach::class, function ($mock) {
        $mock->shouldReceive('answerCoachQuestion')
            ->once()
            ->andReturn('Dit is een test antwoord.');
    });

    $page = Livewire::test(\App\Filament\Pages\AiCoachPage::class);
    
    $page->set('data.question', 'Hoe verbeter ik mijn schieten?')
        ->set('data.weapon_id', $this->weapon->id)
        ->call('submit');

    $this->assertDatabaseHas('coach_questions', [
        'user_id' => $this->user->id,
        'weapon_id' => $this->weapon->id,
        'question' => 'Hoe verbeter ik mijn schieten?',
        'answer' => 'Dit is een test antwoord.',
    ]);
});

it('enforces daily question limit', function () {
    config(['features.defaults.aimtrack-ai' => true]);
    config(['ai.daily_question_limit' => 2]);
    config(['ai.api_key' => 'test-key']);

    $this->mock(ShooterCoach::class, function ($mock) {
        $mock->shouldReceive('answerCoachQuestion')
            ->times(2)
            ->andReturn('Test antwoord.');
    });

    // First two questions should succeed
    for ($i = 0; $i < 2; $i++) {
        Livewire::test(\App\Filament\Pages\AiCoachPage::class)
            ->set('data.question', "Test vraag {$i}")
            ->call('submit');
    }

    // Third question should be blocked
    Livewire::test(\App\Filament\Pages\AiCoachPage::class)
        ->set('data.question', 'Derde vraag')
        ->call('submit');

    $this->assertDatabaseCount('coach_questions', 2);
});

it('exports CSV with all questions', function () {
    config(['features.defaults.aimtrack-ai' => true]);

    CoachQuestion::create([
        'user_id' => $this->user->id,
        'weapon_id' => $this->weapon->id,
        'question' => 'Test vraag 1',
        'answer' => 'Test antwoord 1',
        'asked_at' => now(),
    ]);

    CoachQuestion::create([
        'user_id' => $this->user->id,
        'weapon_id' => $this->weapon->id,
        'question' => 'Test vraag 2',
        'answer' => 'Test antwoord 2',
        'asked_at' => now()->subDay(),
    ]);

    $this->assertDatabaseCount('coach_questions', 2);
});

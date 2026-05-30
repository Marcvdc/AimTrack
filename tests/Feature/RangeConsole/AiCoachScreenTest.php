<?php

declare(strict_types=1);

use App\Filament\Pages\CoachPage;
use App\Models\Session;
use App\Models\SessionWeapon;
use App\Models\User;
use App\Models\Weapon;
use App\Support\Features\AimtrackFeatureToggle;
use App\Support\UserOnboardingState;
use EslamRedaDiv\FilamentCopilot\Models\CopilotConversation;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->mock(AimtrackFeatureToggle::class, function ($mock): void {
        $mock->shouldReceive('aiEnabled')->andReturn(true);
        $mock->shouldReceive('aiDisabled')->andReturn(false);
    });
});

/**
 * De 3-koloms Range Console coach-view is sinds Fase 2 gepoort achter de
 * AI-coach drempel (>= 3 sessies). Onder de drempel toont CoachPage de
 * Fase 2 "te weinig data" empty-state (zie EmptyStates\AiCoachThresholdTest).
 */
function unlockCoach(User $user): void
{
    Session::factory()->for($user)->count(UserOnboardingState::aiCoachThreshold())->create([
        'date' => now()->subDays(2),
        'range_name' => 'SV Diemen',
    ]);
}

it('renders the 3-column layout once the AI-coach threshold is reached', function (): void {
    $user = User::factory()->create();
    unlockCoach($user);

    Livewire::actingAs($user)
        ->test(CoachPage::class)
        ->assertOk()
        ->assertSee('data-testid="ai-coach-chat-ready"', escape: false)
        ->assertSee('RECENTE GESPREKKEN')
        ->assertSee('Stel een vraag aan je coach')
        ->assertSee('VOORBEELDVRAGEN')
        ->assertSee('CONTEXT IN GESPREK')
        ->assertSee('PRIVACY')
        ->assertSee('Open de chat')
        ->assertSee('Nog geen gesprekken met de coach')
        ->assertDontSee('data-testid="ai-coach-threshold"', escape: false);
});

it('lists recent Copilot conversations on the left rail', function (): void {
    $user = User::factory()->create();
    unlockCoach($user);

    foreach (range(1, 3) as $i) {
        CopilotConversation::query()->create([
            'participant_type' => $user->getMorphClass(),
            'participant_id' => $user->getKey(),
            'panel_id' => 'admin',
            'title' => "Gesprek met onderwerp #{$i}",
            'updated_at' => now()->subHours($i),
            'created_at' => now()->subHours($i),
        ]);
    }

    Livewire::actingAs($user)
        ->test(CoachPage::class)
        ->assertSee('Gesprek met onderwerp #1')
        ->assertSee('Gesprek met onderwerp #2')
        ->assertSee('Gesprek met onderwerp #3');
});

it('shows the last session and top weapon in the context rail when data exists', function (): void {
    $user = User::factory()->create();
    $weapon = Weapon::factory()->for($user)->create(['name' => 'Walther LP500', 'caliber' => '4.5 mm']);

    foreach (range(1, 3) as $i) {
        $session = Session::factory()->for($user)->create([
            'date' => now()->subDays($i),
            'range_name' => 'SV Diemen',
        ]);
        SessionWeapon::factory()->for($session)->for($weapon)->create();
    }

    Livewire::actingAs($user)
        ->test(CoachPage::class)
        ->assertSee('SV Diemen')
        ->assertSee('Walther LP500')
        ->assertSee('4.5 mm');
});

it('scopes conversations to the participant user', function (): void {
    $user = User::factory()->create();
    unlockCoach($user);

    $other = User::factory()->create();
    CopilotConversation::query()->create([
        'participant_type' => $other->getMorphClass(),
        'participant_id' => $other->getKey(),
        'panel_id' => 'admin',
        'title' => 'Geheim gesprek van iemand anders',
    ]);

    Livewire::actingAs($user)
        ->test(CoachPage::class)
        ->assertDontSee('Geheim gesprek van iemand anders');
});

<?php

declare(strict_types=1);

use App\Filament\Pages\CoachPage;
use App\Models\Session;
use App\Models\SessionWeapon;
use App\Models\User;
use App\Models\Weapon;
use App\Support\Features\AimtrackFeatureToggle;
use EslamRedaDiv\FilamentCopilot\Models\CopilotConversation;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->mock(AimtrackFeatureToggle::class, function ($mock): void {
        $mock->shouldReceive('aiEnabled')->andReturn(true);
        $mock->shouldReceive('aiDisabled')->andReturn(false);
    });
});

it('renders the 3-column layout with rails and CTA for an empty user', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CoachPage::class)
        ->assertOk()
        ->assertSee('RECENTE GESPREKKEN')
        ->assertSee('Stel een vraag aan je coach')
        ->assertSee('VOORBEELDVRAGEN')
        ->assertSee('CONTEXT IN GESPREK')
        ->assertSee('PRIVACY')
        ->assertSee('Open coach')
        ->assertSee('Geen sessies of wapens gevonden')
        ->assertSee('Nog geen gesprekken met de coach');
});

it('lists recent Copilot conversations on the left rail', function (): void {
    $user = User::factory()->create();

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

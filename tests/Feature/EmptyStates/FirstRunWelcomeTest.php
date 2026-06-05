<?php

declare(strict_types=1);

use App\Filament\Pages\Dashboard;
use App\Models\Session;
use App\Models\User;
use App\Models\Weapon;
use Livewire\Livewire;

test('dashboard renders first-run welcome takeover when user has no sessions or weapons', function (): void {
    $user = User::factory()->create(['email_verified_at' => null]);

    $this->actingAs($user);

    $response = $this->get('/admin');

    $response->assertOk();
    $response->assertSee('data-testid="first-run-welcome"', escape: false);
    $response->assertSee('Klaar voor je', escape: false);
    $response->assertSee('eerste sessie?', escape: false);
    $response->assertSee('● WELKOM', escape: false);
});

test('dashboard does not render welcome when user has a weapon', function (): void {
    $user = User::factory()->create();
    Weapon::factory()->for($user)->create();

    $this->actingAs($user);

    $response = $this->get('/admin');

    $response->assertOk();
    $response->assertDontSee('data-testid="first-run-welcome"', escape: false);
});

test('dashboard does not render welcome when user has a session', function (): void {
    $user = User::factory()->create();
    Session::factory()->for($user)->create();

    $this->actingAs($user);

    $response = $this->get('/admin');

    $response->assertOk();
    $response->assertDontSee('data-testid="first-run-welcome"', escape: false);
});

test('welcome page title is the dedicated welcome string for first-run users', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = new Dashboard;

    expect($page->getTitle())->toBe('Welkom bij AimTrack');
});

test('welcome renders three onboarding steps with the wapen step pending and current', function (): void {
    $user = User::factory()->create(['email_verified_at' => null]);
    $this->actingAs($user);

    $response = $this->get('/admin');

    $response->assertSee('data-testid="first-run-step-1"', escape: false);
    $response->assertSee('data-testid="first-run-step-2"', escape: false);
    $response->assertSee('data-testid="first-run-step-3"', escape: false);
    $response->assertSee('Voeg je eerste wapen toe', escape: false);
    $response->assertSee('Maak je profiel af', escape: false);
    $response->assertSee('Log je eerste sessie', escape: false);
    $response->assertSee('first-run-step-current', escape: false);
});

test('welcome marks ONLY the profile step done when email verified and no weapon or session', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user);

    $html = (string) $this->get('/admin')->getContent();

    $stepIsDone = function (int $step) use ($html): bool {
        if (! preg_match('/data-testid="first-run-step-'.$step.'"[^>]*\bclass="([^"]*)"/', $html, $matches)) {
            return false;
        }

        return str_contains($matches[1], 'first-run-step-done');
    };

    expect($stepIsDone(1))->toBeFalse()  // wapen-stap: nog niet gedaan
        ->and($stepIsDone(2))->toBeTrue()  // profiel-stap: gedaan (email geverifieerd)
        ->and($stepIsDone(3))->toBeFalse(); // sessie-stap: nog niet gedaan
});

test('welcome continue CTA points to weapon-create when user has no weapon', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/admin');

    $response->assertSee(\App\Filament\Resources\WeaponResource::getUrl('create'), escape: false);
    $response->assertSee('Verder waar ik was', escape: false);
});

test('seedDemoDataAction triggers DemoDataSeeder for the current user', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    expect($user->fresh()->demo_data_seeded_at)->toBeNull();

    Livewire::test(Dashboard::class)
        ->callAction('seedDemoData');

    expect($user->fresh()->demo_data_seeded_at)->not->toBeNull()
        ->and($user->weapons()->count())->toBe(3)
        ->and($user->sessions()->count())->toBe(5);
});

test('dashboard renders the stats/leergebieden/KNSA overview when the user has data', function (): void {
    $user = User::factory()->create();
    Weapon::factory()->for($user)->create();

    $this->actingAs($user);

    $response = $this->get('/admin');

    $response->assertOk();
    $response->assertDontSee('● WELKOM', escape: false);
    $response->assertSee('Leergebieden · voortgang');
    $response->assertSee('KNSA · kennisbank');
    // De gefaalde-queue-jobs-widget is geen gebruikersinformatie meer.
    $response->assertDontSee('queue draait zonder fouten');
});

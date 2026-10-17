<?php

use App\Models\User;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Auth\Pages\Register;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * Het panel leest AUTH_REGISTRATION_ENABLED bij het booten en registreert de
 * routes dan wel of niet. Een config()-override in de test komt te laat, dus
 * de app wordt met de env-waarde opnieuw opgebouwd.
 */
function bootWithRegistration(bool $enabled): void
{
    $value = $enabled ? 'true' : 'false';
    putenv("AUTH_REGISTRATION_ENABLED={$value}");
    $_ENV['AUTH_REGISTRATION_ENABLED'] = $value;
    $_SERVER['AUTH_REGISTRATION_ENABLED'] = $value;

    test()->refreshApplication();
    test()->refreshDatabase();
    test()->withoutVite();
}

afterEach(function (): void {
    putenv('AUTH_REGISTRATION_ENABLED');
    unset($_ENV['AUTH_REGISTRATION_ENABLED'], $_SERVER['AUTH_REGISTRATION_ENABLED']);
});

it('staat standaard uit', function (): void {
    expect(config('auth.registration_enabled'))->toBeFalse()
        ->and(Filament::getPanel('admin')->hasRegistration())->toBeFalse()
        ->and(Filament::getPanel('admin')->hasEmailVerification())->toBeFalse()
        ->and(Filament::getPanel('admin')->isEmailVerificationRequired())->toBeFalse();
});

it('geeft een 404 op de registratieroute als de vlag uit staat', function (): void {
    $this->get('/admin/register')->assertNotFound();
});

it('toont geen registratielink op de loginpagina als de vlag uit staat', function (): void {
    $this->get('/admin/login')
        ->assertSuccessful()
        ->assertDontSee('/admin/register');
});

it('laat een bestaande ongeverifieerde gebruiker binnen als de vlag uit staat', function (): void {
    $user = User::factory()->create(['email_verified_at' => null]);

    $this->actingAs($user)->get('/admin')->assertSuccessful();
});

it('opent de registratiepagina en de link op de loginpagina als de vlag aan staat', function (): void {
    bootWithRegistration(true);

    expect(Filament::getPanel('admin')->hasRegistration())->toBeTrue()
        ->and(Filament::getPanel('admin')->isEmailVerificationRequired())->toBeTrue();

    $this->get('/admin/register')->assertSuccessful();
    $this->get('/admin/login')->assertSee('/admin/register');
});

it('registreert een nieuwe gebruiker en stuurt een verificatiemail als de vlag aan staat', function (): void {
    bootWithRegistration(true);
    Notification::fake();

    Livewire::test(Register::class)
        ->fillForm([
            'name' => 'Nieuwe Schutter',
            'email' => 'nieuw@example.com',
            'password' => 'een-lang-wachtwoord',
            'passwordConfirmation' => 'een-lang-wachtwoord',
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'nieuw@example.com')->sole();

    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('stuurt een ongeverifieerde gebruiker naar de verificatieprompt als de vlag aan staat', function (): void {
    bootWithRegistration(true);
    $user = User::factory()->create(['email_verified_at' => null]);

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect(Filament::getPanel('admin')->getEmailVerificationPromptUrl());
});

it('laat een geverifieerde gebruiker gewoon binnen als de vlag aan staat', function (): void {
    bootWithRegistration(true);
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertSuccessful();
});

it('verifieert het e-mailadres via de link uit de mail als de vlag aan staat', function (): void {
    bootWithRegistration(true);
    $user = User::factory()->create(['email_verified_at' => null]);

    $this->actingAs($user)
        ->get(Filament::getPanel('admin')->getVerifyEmailUrl($user))
        ->assertRedirect();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

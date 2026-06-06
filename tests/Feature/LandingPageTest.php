<?php

use App\Models\AiReflection;
use App\Models\Session;
use App\Models\SessionWeapon;
use Illuminate\Support\Facades\Cache;

// Fase 3 — Signal Mint marketing landing. Herzien (sessie 2): toont echte
// instance-aggregaten (geen pure mock), met lege-staat-fallback en een
// configureerbare club. App is gratis → geen prijzen/trial-taal.

test('landing page renders for guests without auth or seeded data', function (): void {
    $this->get('/')->assertOk();
});

test('landing page shows the Signal Mint hero copy', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('Je schietsessies,', escape: false)
        ->assertSee('scherp in beeld.', escape: false)
        ->assertSee('AI-reflectie', escape: false);
});

test('landing page nav links the login and welcome routes', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('Inloggen', escape: false)
        ->assertSee('/admin/login', escape: false)
        ->assertSee(route('welcome'), escape: false);
});

test('landing page uses free-tier CTA copy, not trial or pricing language', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('Aan de slag', escape: false)
        ->assertSee('Gratis aan de slag', escape: false)
        ->assertDontSee('Probeer gratis')
        ->assertDontSee('30 dagen');
});

test('landing page trust strip shows the configured club, default SSV Scherpschutters', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('Gebruikt door sportschutters bij', escape: false)
        ->assertSee('SSV Scherpschutters', escape: false)
        ->assertDontSee('SV Diemen');
});

test('landing page club name is configurable', function (): void {
    config()->set('landing.club', 'PSV De Roos');
    config()->set('landing.partner_clubs', ['SV Tweede Club']);

    $this->get('/')
        ->assertOk()
        ->assertSee('PSV De Roos', escape: false)
        ->assertSee('SV Tweede Club', escape: false)
        ->assertDontSee('SSV Scherpschutters');
});

test('landing page hero shows real instance aggregates when data exists', function (): void {
    Cache::flush();

    $session = Session::factory()->create(['date' => now()]);
    SessionWeapon::factory()->for($session)->create(['rounds_fired' => 137]);
    AiReflection::factory()->for($session)->create();

    $this->get('/')
        ->assertOk()
        ->assertSee('SESSIES', escape: false)
        ->assertSee('SCHOTEN', escape: false)
        ->assertSee('137', escape: false)        // som van rounds_fired (SessionWeapon)
        ->assertSee('AI-REFLECTIES', escape: false)
        ->assertDontSee('Begin je eerste sessie');
});

test('landing page hero falls back to a start state on an empty instance', function (): void {
    Cache::flush();

    $this->get('/')
        ->assertOk()
        ->assertSee('Begin je eerste sessie', escape: false)
        ->assertDontSee('GEM. SCORE');
});

test('landing page inlines the AimTrack design tokens and webfonts', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('<style id="aimtrack-tokens">', escape: false)
        ->assertSee('--at-accent:    #64f4b3;', escape: false)
        ->assertSee('JetBrains+Mono', escape: false);
});

test('landing page no longer shows the pre-handoff live-stats content', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertDontSee('Ring heatmap')
        ->assertDontSee('KNSA referenties')
        ->assertDontSee('Sessies deze maand')
        ->assertDontSee('Gem. score')
        ->assertDontSee('Schiet- en Wedstrijdreglementen');
});

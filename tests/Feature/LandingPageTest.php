<?php

// Fase 3 — Signal Mint marketing landing. The page is fully static guest-facing
// marketing copy (no live DB stats). Assertions grow per BUILD-stap; the
// controller/dead-code simplification + final coverage land in Stap 5.

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
        ->assertSee('Probeer gratis', escape: false)
        ->assertSee('/admin/login', escape: false)
        ->assertSee(route('welcome'), escape: false);
});

test('landing page shows the trust strip', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('Gebruikt door sportschutters bij', escape: false)
        ->assertSee('SV Diemen', escape: false);
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

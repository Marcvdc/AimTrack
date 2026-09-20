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

test('landing page hides the trust strip when no club is configured', function (): void {
    config()->set('landing.club', '');
    config()->set('landing.partner_clubs', []);

    // Zonder ingestelde club heeft de instance geen club die AimTrack gebruikt.
    // De strip blijft dan weg in plaats van een naam te noemen die nergens op slaat (#131).
    $this->get('/')
        ->assertOk()
        ->assertDontSee('Gebruikt door sportschutters bij')
        ->assertDontSee('SSV Scherpschutters')
        ->assertDontSee('SV Diemen');
});

test('landing page trust strip appears once a club is configured', function (): void {
    config()->set('landing.club', 'PSV De Roos');

    $this->get('/')
        ->assertOk()
        ->assertSee('Gebruikt door sportschutters bij', escape: false)
        ->assertSee('PSV De Roos', escape: false);
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
    // Losse asserts i.p.v. exacte whitespace — de verbatim-token-inhoud wordt
    // gedekt door BrandComponentsTest ("head-assets emits the full token
    // stylesheet verbatim"); hier toetsen we alleen dát ze geïnlined zijn.
    $this->get('/')
        ->assertOk()
        ->assertSee('<style id="aimtrack-tokens">', escape: false)
        ->assertSee('--at-accent', escape: false)
        ->assertSee('#64f4b3', escape: false)
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

test('landing page renders the six feature cards', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('Wat je krijgt', escape: false)
        ->assertSee('01 · SESSIES', escape: false)
        ->assertSee('02 · AI-COACH', escape: false)
        ->assertSee('03 · TRENDS', escape: false)
        ->assertSee('04 · EXPORT', escape: false)
        ->assertSee('05 · PRIVACY', escape: false)
        ->assertSee('06 · WAPENS', escape: false);
});

test('landing page export card carries the T4 monogram stamp', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('EXPORT OK', escape: false)
        ->assertSee('aimtrack-monogram-stamp', escape: false);
});

test('landing page renders the AI-coach deep-dive with a bracket frame', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('jouw cijfers', escape: false)
        ->assertSee('aimtrack-bracket-frame', escape: false)
        ->assertSee('SCORE-DRIFT', escape: false);
});

test('landing page does not claim that data stays on the server', function (): void {
    // De AI-coach stuurt sessie- en wapengegevens naar Anthropic, dus deze belofte
    // was onwaar en mag niet stilletjes terugkomen (#132).
    $this->get('/')
        ->assertOk()
        ->assertDontSee('je data verlaat de server niet')
        ->assertDontSee('Geen data verlaat de server')
        ->assertDontSee('Alles draait lokaal')
        ->assertDontSee('data is van jou, altijd');
});

test('landing page names Anthropic as the destination of AI data', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('api.anthropic.com', escape: false)
        ->assertSee('zonder eigen Claude-key geen enkele call', escape: false);
});

test('landing page carries no WM-4 or compliance claim', function (): void {
    // WM-4 is het verlofdocument van de korpschef, geen standaard waaraan software
    // kan voldoen (#131). Ook de conformiteitstaal eromheen blijft weg.
    $this->get('/')
        ->assertOk()
        ->assertDontSee('WM-4')
        ->assertDontSee('WM4')
        ->assertDontSee('Wet-conforme')
        ->assertDontSee('conforme administratie')
        ->assertDontSee('klaar voor inlevering');
});

test('landing page does not advertise services that do not exist', function (): void {
    // Er is geen NL-cloud en AimTrack levert geen keuringsbrief (#131).
    $this->get('/')
        ->assertOk()
        ->assertDontSee('NL-cloud')
        ->assertDontSee('keuringsbrief');
});

test('landing page renders the self-hosted strip in place of pricing', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('Eén command, eigen instance.', escape: false)
        ->assertSee('docker compose up -d', escape: false)
        ->assertSee('MIT-licentie', escape: false)
        ->assertSee('Gratis · voor altijd', escape: false);
});

test('landing page renders a footer with the MIT meta line', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('MIT · open-source · NL', escape: false);
});

test('landing page footer links Docs, Changelog and Contact are live, not muted placeholders', function (): void {
    $this->get('/')
        ->assertOk()
        // Docs → GitHub user-docs map; Changelog → GitHub releases; Contact → eigen route.
        ->assertSee('https://github.com/Marcvdc/AimTrack/tree/main/docs/user', escape: false)
        ->assertSee('https://github.com/Marcvdc/AimTrack/releases', escape: false)
        ->assertSee(route('contact'), escape: false)
        // De bewust-dode placeholders zijn weg.
        ->assertDontSee('mk-footer-muted');
});

test('landing page GitHub links use the canonical Marcvdc casing', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('github.com/Marcvdc/AimTrack', escape: false)
        ->assertDontSee('github.com/marcvdc/AimTrack');
});

test('landing page carries no pricing section, tiers or trial language', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertDontSee('POPULAIR')
        ->assertDontSee('/ maand')
        ->assertDontSee('Vraag offerte')
        ->assertDontSee('Eerlijk, en voor altijd')
        ->assertDontSee('Prijzen')
        ->assertDontSee('€');
});

test('landing page Sessies card falls back to an empty state without sessions', function (): void {
    Cache::flush();

    $this->get('/')
        ->assertOk()
        ->assertSee('Nog geen sessies', escape: false)
        // Lege instance → de Trends-sparkline tekent een nullijn, geen
        // area-gradient (die id verschijnt alleen bij fill + data).
        ->assertDontSee('atspark-');
});

test('landing page feature cards surface live session data when it exists', function (): void {
    Cache::flush();

    // Twee sessie-datums → de Trends-sparkline heeft ≥2 punten en rendert de
    // gevulde area-gradient (de component vereist minstens 2 datapunten).
    $earlier = Session::factory()->create(['date' => now()->subDay(), 'range_name' => 'Baan Demo Nul']);
    SessionWeapon::factory()->for($earlier)->create(['rounds_fired' => 50]);

    $session = Session::factory()->create(['date' => now(), 'range_name' => 'Baan Demo One']);
    SessionWeapon::factory()->for($session)->create(['rounds_fired' => 73]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Baan Demo One', escape: false)   // recente-sessies-kaart (live)
        ->assertSee('SCHOTEN · PER SESSIE', escape: false) // trends-kaart label
        // De gevulde Trends-sparkline rendert de area-gradient (atspark-id)
        // → bewijst dat het live trendSeries-data-pad daadwerkelijk liep.
        ->assertSee('atspark-', escape: false)
        ->assertDontSee('Nog geen sessies');
});

<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Weapon;
use Illuminate\Support\Facades\Blade;

/*
 * Issue #104 — Custom onderdelen mobile-responsive maken.
 *
 * Deze tests borgen het fluid-recept (#104): de gedeelde CSS-rule, de fluid
 * type-tokens, de `fluid`-prop op sparkline, en de responsive grid-utilities.
 * De bron-van-waarheid is resources/css/aimtrack-tokens.css, die zowel de
 * publieke landing als het Filament-panel voedt.
 */

test('sparkline emits fixed width/height attributes by default', function (): void {
    $html = Blade::render('<x-aimtrack.sparkline :data="[1, 2, 3]" :width="280" :height="70" />');

    expect($html)
        ->toContain('aimtrack-sparkline')
        ->toContain('width="280"')
        ->toContain('height="70"')
        ->toContain('viewBox="0 0 280 70"')
        ->not->toContain('width: 100%');
});

test('sparkline fluid prop swaps fixed dimensions for a capped percentage width', function (): void {
    $html = Blade::render('<x-aimtrack.sparkline :data="[1, 2, 3]" :width="380" :height="50" fluid />');

    expect($html)
        ->toContain('width: 100%')
        ->toContain('max-width: 380px')
        ->toContain('height: auto')
        ->toContain('viewBox="0 0 380 50"')
        ->not->toContain('width="380"')
        ->not->toContain('height="50"');
});

test('svg primitives carry the shared fluid hook class', function (): void {
    expect(Blade::render('<x-aimtrack.reticle />'))->toContain('aimtrack-reticle');
    expect(Blade::render('<x-aimtrack.at-mark />'))->toContain('aimtrack-at-mark');
    expect(Blade::render('<x-aimtrack.icon name="target" />'))->toContain('aimtrack-icon');
    expect(Blade::render('<x-aimtrack.sparkline :data="[1, 2]" />'))->toContain('aimtrack-sparkline');
    expect(Blade::render('<x-aimtrack.target-rings />'))->toContain('aimtrack-target-rings');
});

test('ring-medaillon caps its width instead of hardcoding pixel dimensions', function (): void {
    $html = Blade::render('<x-aimtrack.ring-medaillon size="160" />');

    expect($html)
        ->toContain('width: 100%')
        ->toContain('max-width: 160px')
        ->toContain('aspect-ratio: 1')
        ->not->toContain('height: 160px');
});

test('design tokens stylesheet defines the shared fluid svg rule and overflow guard', function (): void {
    $css = file_get_contents(resource_path('css/aimtrack-tokens.css'));

    expect($css)
        ->toContain('.aimtrack-sparkline,')
        ->toContain('.aimtrack-reticle,')
        ->toContain('.aimtrack-at-mark')
        ->toContain('max-width: 100%')
        ->toContain('height: auto')
        ->toContain('html {')
        ->toContain('overflow-x: hidden');
});

test('design tokens stylesheet exposes fluid data and display type tokens', function (): void {
    $css = file_get_contents(resource_path('css/aimtrack-tokens.css'));

    expect($css)
        ->toContain('--at-data-lg: clamp(')
        ->toContain('--at-data-xl: clamp(')
        ->toContain('--at-display-lg-fluid: clamp(')
        ->toContain('--at-display-md-fluid: clamp(');
});

test('design tokens stylesheet defines responsive grid and touch utilities with the 768/520 breakpoints', function (): void {
    $css = file_get_contents(resource_path('css/aimtrack-tokens.css'));

    expect($css)
        ->toContain('.at-grid-main-aside')
        ->toContain('.at-grid-aside-main')
        ->toContain('.at-grid-stats')
        ->toContain('.at-grid-triptych')
        ->toContain('.at-touch')
        ->toContain('@media (max-width: 768px)')
        ->toContain('@media (max-width: 520px)');
});

test('landing page guards the html element against horizontal overflow', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('html { scroll-behavior: smooth; overflow-x: hidden; }', escape: false);
});

test('landing page renders the chat drift sparkline as a fluid svg', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('aimtrack-sparkline', escape: false)
        ->assertSee('width: 100%', escape: false);
});

test('dashboard overview renders the fluid stat and main-aside grids', function (): void {
    $user = User::factory()->create();
    Weapon::factory()->for($user)->create();

    $this->actingAs($user);

    $this->get('/admin')
        ->assertOk()
        ->assertDontSee('data-testid="first-run-welcome"', escape: false)
        ->assertSee('at-grid-main-aside', escape: false)
        ->assertSee('at-grid-stats', escape: false);
});

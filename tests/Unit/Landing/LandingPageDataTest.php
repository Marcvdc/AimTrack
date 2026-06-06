<?php

use App\Models\AiReflection;
use App\Models\Session;
use App\Models\SessionWeapon;
use App\Support\Landing\LandingPageData;
use Illuminate\Support\Facades\Cache;

test('stats reports has_data false and zeroes on an empty instance', function (): void {
    Cache::flush();

    $stats = app(LandingPageData::class)->stats();

    expect($stats['has_data'])->toBeFalse()
        ->and($stats['total_sessions'])->toBe(0)
        ->and($stats['total_rounds'])->toBe(0)
        ->and($stats['ai_reflections'])->toBe(0);
});

test('stats aggregates sessions, rounds fired and reflections instance-wide', function (): void {
    Cache::flush();

    $session = Session::factory()->create(['date' => now()]);
    SessionWeapon::factory()->for($session)->create(['rounds_fired' => 40]);
    SessionWeapon::factory()->for($session)->create(['rounds_fired' => 60]);
    AiReflection::factory()->for($session)->create();

    $stats = app(LandingPageData::class)->stats();

    expect($stats['has_data'])->toBeTrue()
        ->and($stats['total_sessions'])->toBe(1)
        ->and($stats['total_rounds'])->toBe(100)
        ->and($stats['ai_reflections'])->toBe(1);
});

test('stats are cached under the landing.stats key until flushed', function (): void {
    Cache::flush();
    $data = app(LandingPageData::class);

    expect($data->stats()['total_sessions'])->toBe(0);

    Session::factory()->create(['date' => now()]);

    // Still served from cache (stale) — proves the remember() wrapper.
    expect($data->stats()['total_sessions'])->toBe(0);
    expect(Cache::has('landing.stats'))->toBeTrue();

    Cache::flush();
    expect($data->stats()['total_sessions'])->toBe(1);
});

test('club defaults to SSV Scherpschutters and honours config', function (): void {
    expect(app(LandingPageData::class)->club())->toBe('SSV Scherpschutters');

    config()->set('landing.club', 'PSV De Roos');
    expect(app(LandingPageData::class)->club())->toBe('PSV De Roos');
});

test('partnerClubs filters empties and honours config', function (): void {
    expect(app(LandingPageData::class)->partnerClubs())->toBe([]);

    config()->set('landing.partner_clubs', ['SV Een', '', 'SV Twee', null]);
    expect(app(LandingPageData::class)->partnerClubs())->toBe(['SV Een', 'SV Twee']);
});

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

test('trendSeries is empty on an empty instance', function (): void {
    Cache::flush();

    expect(app(LandingPageData::class)->trendSeries())->toBe([]);
});

test('trendSeries sums rounds fired per session date, chronological', function (): void {
    Cache::flush();

    $older = Session::factory()->create(['date' => now()->subDays(2)]);
    SessionWeapon::factory()->for($older)->create(['rounds_fired' => 30]);
    SessionWeapon::factory()->for($older)->create(['rounds_fired' => 20]);

    $newer = Session::factory()->create(['date' => now()->subDay()]);
    SessionWeapon::factory()->for($newer)->create(['rounds_fired' => 75]);

    expect(app(LandingPageData::class)->trendSeries())->toBe([50, 75]);
});

test('trendSeries keeps only the last N session dates', function (): void {
    Cache::flush();

    foreach (range(1, 5) as $offset) {
        $session = Session::factory()->create(['date' => now()->subDays($offset)]);
        SessionWeapon::factory()->for($session)->create(['rounds_fired' => $offset * 10]);
    }

    // Oldest first → [50, 40, 30, 20, 10]; last 2 dates → [20, 10].
    expect(app(LandingPageData::class)->trendSeries(points: 2))->toBe([20, 10]);
});

test('recentSessions is empty on an empty instance', function (): void {
    Cache::flush();

    expect(app(LandingPageData::class)->recentSessions())->toBe([]);
});

test('recentSessions returns the latest sessions with range and total rounds', function (): void {
    Cache::flush();

    $old = Session::factory()->create(['date' => now()->subWeek(), 'range_name' => 'Oude Baan']);
    SessionWeapon::factory()->for($old)->create(['rounds_fired' => 10]);

    $recent = Session::factory()->create(['date' => now(), 'range_name' => 'Baan Centrum']);
    SessionWeapon::factory()->for($recent)->create(['rounds_fired' => 40]);
    SessionWeapon::factory()->for($recent)->create(['rounds_fired' => 5]);

    $rows = app(LandingPageData::class)->recentSessions(limit: 1);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['range'])->toBe('Baan Centrum')
        ->and($rows[0]['rounds'])->toBe(45)
        ->and($rows[0]['date'])->toBe(now()->format('d-m'));
});

test('recentSessions falls back to location, then a dash, for the range label', function (): void {
    Cache::flush();

    Session::factory()->create([
        'date' => now(),
        'range_name' => null,
        'location' => 'Amsterdam',
    ]);

    expect(app(LandingPageData::class)->recentSessions()[0]['range'])->toBe('Amsterdam');
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

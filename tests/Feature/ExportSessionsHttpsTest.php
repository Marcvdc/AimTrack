<?php

use App\Models\Session;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-01-26 10:00:00');
    URL::forceScheme(null);
    URL::forceRootUrl(null);
});

afterEach(function (): void {
    URL::forceScheme(null);
    URL::forceRootUrl(null);
});

it('serves session export downloads over https when forcing is enabled', function (): void {
    config()->set('app.force_https', true);
    URL::forceScheme('https');
    URL::forceRootUrl('https://aimtrack.test');
    config()->set('app.url', 'https://aimtrack.test');

    $user = User::factory()->create();

    Session::factory()
        ->for($user)
        ->create([
            'date' => now()->subDay(),
        ]);

    $this->actingAs($user);

    $from = now()->subDays(7)->toDateString();
    $to = now()->toDateString();

    $url = route('exports.sessions.download', [
        'from' => $from,
        'to' => $to,
        'format' => 'csv',
    ]);

    expect($url)->toStartWith('https://');

    $response = $this->withHeader('X-Forwarded-Proto', 'https')->get($url);

    $response->assertOk();
    $response->assertHeaderContains('content-type', 'text/csv');
    $response->assertHeaderContains('content-disposition', '.csv');
});

it('serves session export downloads over http when forcing is disabled', function (): void {
    config()->set('app.force_https', false);
    URL::forceScheme(null);
    URL::forceRootUrl('http://aimtrack.test');
    config()->set('app.url', 'http://aimtrack.test');

    $user = User::factory()->create();

    Session::factory()
        ->for($user)
        ->create([
            'date' => now()->subDay(),
        ]);

    $this->actingAs($user);

    $from = now()->subDays(7)->toDateString();
    $to = now()->toDateString();

    $query = http_build_query([
        'from' => $from,
        'to' => $to,
        'format' => 'csv',
    ]);

    $response = $this->get("http://aimtrack.test/exports/sessions/download?{$query}");

    $response->assertOk();
    $response->assertHeaderContains('content-type', 'text/csv');
    $response->assertHeaderMissing('location');
});

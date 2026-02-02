<?php

use App\Models\AiReflection;
use App\Models\Session;
use App\Models\SessionShot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

it('renders landing page stats and knsa links from live data', function () {
    Cache::flush();

    $now = CarbonImmutable::parse('2026-01-24 10:00:00');
    CarbonImmutable::setTestNow($now);

    $session = Session::factory()->create(['date' => $now->subDay()]);
    Session::factory()->create(['date' => $now->subDays(3)]);
    $oldSession = Session::factory()->create(['date' => $now->subMonth()->subDay()]);

    SessionShot::factory()->for($session)->state(['score' => 8, 'ring' => 8, 'turn_index' => 0, 'shot_index' => 0, 'created_at' => $now])->create();
    SessionShot::factory()->for($session)->state(['score' => 9, 'ring' => 9, 'turn_index' => 0, 'shot_index' => 1, 'created_at' => $now])->create();
    SessionShot::factory()->for($session)->state(['score' => 10, 'ring' => 10, 'turn_index' => 0, 'shot_index' => 2, 'created_at' => $now])->create();

    AiReflection::factory()->for($session)->state(['created_at' => $now])->create();
    AiReflection::factory()->for($oldSession)->state(['created_at' => $now])->create();

    config()->set('landing.knsa_links', [
        [
            'title' => 'Schiet- en Wedstrijdreglementen',
            'description' => 'Regels',
            'url' => 'https://www.knsa.nl/de-knsa/wet-en-regelgeving/schiet-en-wedstrijdreglementen/',
        ],
        [
            'title' => 'KNSA Downloadcenter',
            'description' => 'Checklist',
            'url' => 'https://www.knsa.nl/downloadcenter/',
        ],
    ]);

    $response = $this->get('/');

    $response->assertOk()
        ->assertViewHas('stats', function (array $stats) {
            expect($stats['sessions_this_month'])->toBe(2);
            expect($stats['average_score'])->toBe(9.0);
            expect($stats['ai_reflections_last_30_days'])->toBe(2);
            expect($stats['ring_heatmap'])->toBeArray();
            $rings = collect($stats['ring_heatmap'])->keyBy('ring');
            expect($rings[8]['total'])->toBe(1);
            expect($rings[9]['total'])->toBe(1);
            expect($rings[10]['total'])->toBe(1);

            return true;
        })
        ->assertViewHas('knsaLinks', function (array $links) {
            expect($links)->toHaveCount(2);
            expect($links[0]['title'])->toBe('Schiet- en Wedstrijdreglementen');

            return true;
        })
        ->assertSee('Gem. score')
        ->assertSee('9.0')
        ->assertSee('Schiet- en Wedstrijdreglementen');

    CarbonImmutable::setTestNow();
});

it('falls back to zeros and placeholder when no data configured', function () {
    Cache::flush();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-01-24 10:00:00'));

    config()->set('landing.knsa_links', []);

    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('Sessies deze maand')
        ->assertSee('0')
        ->assertSeeText('Geen KNSA-links geconfigureerd.');

    CarbonImmutable::setTestNow();
});

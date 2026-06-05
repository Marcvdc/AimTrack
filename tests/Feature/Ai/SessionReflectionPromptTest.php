<?php

declare(strict_types=1);

use App\Enums\Deviation;
use App\Models\Session;
use App\Models\SessionShot;
use App\Models\SessionWeapon;
use App\Models\User;
use App\Services\Ai\ShooterCoach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeShooterCoach(): ShooterCoach
{
    return new ShooterCoach(
        driver: 'openai',
        model: 'test-model',
        baseUrl: 'https://api.test/v1',
        apiKey: 'sk-test',
    );
}

function fakeReflectionResponse(): void
{
    Http::fake([
        '*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'summary' => 'Sterke opening, dip in serie 2.',
                        'positives' => ['Openingsritme'],
                        'improvements' => ['Concentratie serie 2'],
                        'next_focus' => 'Adem-reset',
                    ]),
                ],
            ]],
        ], 200),
    ]);
}

test('reflection does not crash on a backed Deviation enum and persists', function (): void {
    fakeReflectionResponse();

    $session = Session::factory()->create(['user_id' => User::factory()]);
    // The Deviation backed enum used to fatal on sprintf('%s', $enum).
    SessionWeapon::factory()->create([
        'session_id' => $session->id,
        'deviation' => Deviation::LEFT,
    ]);

    $reflection = fakeShooterCoach()->generateSessionReflection($session);

    expect($reflection->exists)->toBeTrue()
        ->and($reflection->summary)->not->toBeEmpty();

    Http::assertSent(fn ($request): bool => str_contains(
        $request['messages'][1]['content'] ?? '',
        'afwijking: left',
    ));
});

test('reflection prompt includes numeric shot statistics', function (): void {
    fakeReflectionResponse();

    $session = Session::factory()->create(['user_id' => User::factory()]);
    SessionWeapon::factory()->create(['session_id' => $session->id]);

    // Serie 1 (turn 0) = tienen, serie 2 (turn 1) = achten → een duidelijke dip.
    foreach (range(0, 9) as $i) {
        SessionShot::factory()->create([
            'session_id' => $session->id,
            'turn_index' => 0,
            'shot_index' => $i,
            'ring' => 10,
            'score' => 10,
        ]);
    }
    foreach (range(0, 9) as $i) {
        SessionShot::factory()->create([
            'session_id' => $session->id,
            'turn_index' => 1,
            'shot_index' => $i,
            'ring' => 8,
            'score' => 8,
        ]);
    }

    fakeShooterCoach()->generateSessionReflection($session);

    Http::assertSent(function ($request): bool {
        $prompt = $request['messages'][1]['content'] ?? '';

        return str_contains($prompt, 'Schotstatistiek')
            && str_contains($prompt, 'Totaalscore: 180')
            && str_contains($prompt, 'Serie-scores (per 10): 100 · 80')
            && str_contains($prompt, 'zwakste reeks rond schot 11');
    });
});

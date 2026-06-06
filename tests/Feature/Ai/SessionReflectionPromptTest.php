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
        model: 'claude-haiku-4-5-20251001',
        baseUrl: 'https://api.anthropic.com',
        maxTokens: 1024,
        anthropicVersion: '2023-06-01',
    );
}

function keyedSession(): Session
{
    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-user-key']);

    return Session::factory()->create(['user_id' => $user->id]);
}

function fakeReflectionResponse(): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'summary' => 'Sterke opening, dip in serie 2.',
                    'positives' => ['Openingsritme'],
                    'improvements' => ['Concentratie serie 2'],
                    'next_focus' => 'Adem-reset',
                ]),
            ]],
        ], 200),
    ]);
}

test('reflectie loopt via de Anthropic Messages API met de user-key', function (): void {
    fakeReflectionResponse();

    $session = keyedSession();
    SessionWeapon::factory()->create(['session_id' => $session->id]);

    $reflection = fakeShooterCoach()->generateSessionReflection($session);

    expect($reflection->summary)->toBe('Sterke opening, dip in serie 2.');

    Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
        return str_contains($request->url(), '/v1/messages')
            && $request->hasHeader('x-api-key', 'sk-ant-user-key')
            && $request->hasHeader('anthropic-version', '2023-06-01')
            && $request['model'] === 'claude-haiku-4-5-20251001'
            && isset($request['max_tokens'])
            && is_string($request['system'])
            && $request['messages'][0]['role'] === 'user';
    });
});

test('zonder user-key wordt er geen call gedaan en volgt een nette fallback', function (): void {
    Http::fake();

    $user = User::factory()->create(['anthropic_api_key' => null]);
    $session = Session::factory()->create(['user_id' => $user->id]);

    $reflection = fakeShooterCoach()->generateSessionReflection($session);

    Http::assertNothingSent();
    expect($reflection->summary)->not->toBeEmpty();
});

test('reflection does not crash on a backed Deviation enum and persists', function (): void {
    fakeReflectionResponse();

    $session = keyedSession();
    // The Deviation backed enum used to fatal on sprintf('%s', $enum).
    SessionWeapon::factory()->create([
        'session_id' => $session->id,
        'deviation' => Deviation::LEFT,
    ]);

    $reflection = fakeShooterCoach()->generateSessionReflection($session);

    expect($reflection->exists)->toBeTrue()
        ->and($reflection->summary)->not->toBeEmpty();

    Http::assertSent(fn (\Illuminate\Http\Client\Request $request): bool => str_contains(
        $request['messages'][0]['content'] ?? '',
        'afwijking: left',
    ));
});

test('reflection prompt includes numeric shot statistics', function (): void {
    fakeReflectionResponse();

    $session = keyedSession();
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

    Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
        $prompt = $request['messages'][0]['content'] ?? '';

        return str_contains($prompt, 'Schotstatistiek')
            && str_contains($prompt, 'Totaalscore: 180')
            && str_contains($prompt, 'Serie-scores (per 10): 100 · 80')
            && str_contains($prompt, 'zwakste reeks rond schot 11');
    });
});

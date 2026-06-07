<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Weapon;
use App\Services\Ai\ShooterCoach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('wapen-insight loopt via de Anthropic Messages API met de user-key', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'summary' => 'Consistente prestaties.',
                    'patterns' => ['Lichte links-afwijking'],
                    'suggestions' => ['Controleer grip'],
                ]),
            ]],
        ], 200),
    ]);

    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-user-key']);
    $weapon = Weapon::factory()->create(['user_id' => $user->id]);

    $insight = (new ShooterCoach(
        model: 'claude-haiku-4-5-20251001',
        baseUrl: 'https://api.anthropic.com',
    ))->generateWeaponInsight($weapon);

    expect($insight->summary)->toBe('Consistente prestaties.');

    Http::assertSent(fn (\Illuminate\Http\Client\Request $r): bool => str_contains($r->url(), '/v1/messages')
        && $r->hasHeader('x-api-key', 'sk-ant-user-key')
        && $r['messages'][0]['role'] === 'user');
});

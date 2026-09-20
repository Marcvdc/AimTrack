<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Weapon;
use App\Services\Ai\ShooterCoach;
use App\Support\Ai\AiPrivacyNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// #132: de app beloofde op drie plekken plus in de docs dat data de server niet
// verlaat, terwijl de AI-coach sessie- en wapengegevens naar Anthropic stuurt.
// Deze tests pinnen vast dat die belofte weg is en dat wat er nu staat klopt met
// wat ShooterCoach daadwerkelijk meestuurt.

test('de privacytekst noemt de ontvanger, de uitschakelroute en de gedeelde velden', function (): void {
    $panel = AiPrivacyNotice::panel();

    expect($panel)
        ->toContain('api.anthropic.com')
        ->toContain('FEATURE_AIMTRACK_AI')
        ->toContain('serienummer')
        ->toContain('opslaglocatie')
        ->toContain('vereniging')
        ->and(AiPrivacyNotice::intro())->toContain('api.anthropic.com')
        ->and(AiPrivacyNotice::landingCheckItem())->toContain('api.anthropic.com');
});

test('geen enkele privacytekst belooft nog dat data de server niet verlaat', function (): void {
    $teksten = [
        AiPrivacyNotice::intro(),
        AiPrivacyNotice::panel(),
        AiPrivacyNotice::landingCheckItem(),
    ];

    foreach ($teksten as $tekst) {
        expect($tekst)
            ->not->toContain('verlaat de server')
            ->not->toContain('Alles draait lokaal')
            ->not->toContain('eigen instance');
    }
});

test('de wapenprompt stuurt precies de velden die de privacytekst noemt', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'summary' => 'Consistent.',
                    'patterns' => [],
                    'suggestions' => [],
                ]),
            ]],
        ], 200),
    ]);

    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-user-key']);
    $weapon = Weapon::factory()->create([
        'user_id' => $user->id,
        'serial_number' => 'SN-TEST-4711',
        'storage_location' => 'Kluis A',
    ]);

    (new ShooterCoach(
        model: 'claude-haiku-4-5-20251001',
        baseUrl: 'https://api.anthropic.com',
    ))->generateWeaponInsight($weapon);

    // Gaat het serienummer of de opslaglocatie ooit uit de prompt, dan faalt deze
    // test en moet de privacytekst mee. Zo lopen code en tekst niet uit de pas.
    Http::assertSent(function (\Illuminate\Http\Client\Request $r): bool {
        $prompt = $r['messages'][0]['content'];

        return str_contains($prompt, 'SN-TEST-4711')
            && str_contains($prompt, 'Kluis A');
    });

    expect(AiPrivacyNotice::panel())
        ->toContain('serienummer')
        ->toContain('opslaglocatie');
});

test('zonder API-key gaat er geen enkele call naar buiten', function (): void {
    Http::fake();

    $user = User::factory()->create(['anthropic_api_key' => null]);
    $weapon = Weapon::factory()->create(['user_id' => $user->id]);

    (new ShooterCoach(
        model: 'claude-haiku-4-5-20251001',
        baseUrl: 'https://api.anthropic.com',
    ))->generateWeaponInsight($weapon);

    Http::assertNothingSent();
});

test('de gebruikersdocumentatie vertelt hetzelfde verhaal als de app', function (): void {
    $docs = file_get_contents(base_path('docs/user/README.md'));

    expect($docs)
        ->toContain('api.anthropic.com')
        ->toContain('FEATURE_AIMTRACK_AI')
        ->toContain('serienummer')
        ->toContain('opslaglocatie')
        ->not->toContain('je data verlaat je')
        ->not->toContain('verlaat de server niet');
});

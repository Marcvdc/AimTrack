<?php

declare(strict_types=1);

use App\Services\Vision\TokenUsage;

test('het gebruik wordt uit het antwoord van de API gelezen', function (): void {
    $usage = TokenUsage::fromArray([
        'input_tokens' => 2500,
        'output_tokens' => 14000,
        'cache_read_input_tokens' => 100,
        'cache_creation_input_tokens' => 50,
    ]);

    expect($usage->inputTokens)->toBe(2500)
        ->and($usage->outputTokens)->toBe(14000)
        ->and($usage->cacheReadTokens)->toBe(100);
});

test('een ontbrekend usage-blok levert nullen op in plaats van een fout', function (): void {
    expect(TokenUsage::fromArray(null)->outputTokens)->toBe(0);
});

test('denk-tokens domineren de prijs, want ze tellen als output', function (): void {
    // 2.500 invoer tegen $5 en 14.000 uitvoer tegen $25 per miljoen.
    $kosten = (new TokenUsage(inputTokens: 2500, outputTokens: 14000))->estimatedCost(5.0, 25.0);

    expect(round($kosten, 4))->toBe(0.3625);
});

test('de schatting van 8 cent per foto klopte niet met deze verhoudingen', function (): void {
    // Vastgelegd omdat deze fout geld heeft gekost: bij het denkwerk van Opus 5 zit
    // je een veelvoud boven een schatting die uitgaat van een kort antwoord.
    $kort = (new TokenUsage(inputTokens: 2800, outputTokens: 2500))->estimatedCost(5.0, 25.0);
    $echt = (new TokenUsage(inputTokens: 2800, outputTokens: 15000))->estimatedCost(5.0, 25.0);

    expect(round($kort, 2))->toBe(0.08)
        ->and($echt)->toBeGreaterThan($kort * 4);
});

test('gebruik van meerdere calls telt op', function (): void {
    $totaal = (new TokenUsage(inputTokens: 100, outputTokens: 200))
        ->plus(new TokenUsage(inputTokens: 50, outputTokens: 75));

    expect($totaal->inputTokens)->toBe(150)
        ->and($totaal->outputTokens)->toBe(275);
});

test('cache-lezingen rekenen tegen een tiende van de invoerprijs', function (): void {
    $kosten = (new TokenUsage(cacheReadTokens: 1_000_000))->estimatedCost(5.0, 25.0);

    expect(round($kosten, 2))->toBe(0.5);
});

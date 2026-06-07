<?php

declare(strict_types=1);

use App\Models\User;

it('returns sensible defaults when no preferences are stored', function (): void {
    $user = User::factory()->create();

    expect($user->preference('board_show_rings'))->toBeFalse()
        ->and($user->preference('decimal_notation'))->toBeTrue()
        ->and($user->preference('auto_ai_reflection'))->toBeFalse();
});

it('persists a known preference and reads it back', function (): void {
    $user = User::factory()->create();

    $user->setPreference('board_show_rings', true);

    expect($user->fresh()->preference('board_show_rings'))->toBeTrue();
});

it('ignores unknown preference keys', function (): void {
    $user = User::factory()->create();

    $user->setPreference('totally_unknown', true);

    expect($user->fresh()->preference('totally_unknown'))->toBeNull()
        ->and($user->fresh()->preferences ?? [])->not->toHaveKey('totally_unknown');
});

it('casts the preferences column to an array', function (): void {
    $user = User::factory()->create();

    $user->setPreference('decimal_notation', false);

    expect($user->fresh()->preferences)->toBeArray()
        ->and($user->fresh()->preference('decimal_notation'))->toBeFalse();
});

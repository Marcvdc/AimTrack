<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('slaat de anthropic key encrypted op en geeft hem decrypted terug', function (): void {
    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-secret-123']);

    expect($user->fresh()->anthropic_api_key)->toBe('sk-ant-secret-123');

    $rawColumn = DB::table('users')->where('id', $user->id)->value('anthropic_api_key');
    expect($rawColumn)->not->toBe('sk-ant-secret-123')
        ->and($rawColumn)->not->toBeNull();
});

it('verbergt de anthropic key in serialisatie', function (): void {
    $user = User::factory()->create(['anthropic_api_key' => 'sk-ant-secret-123']);

    expect($user->toArray())->not->toHaveKey('anthropic_api_key');
});

it('casts ai_key_verified_at naar een datetime', function (): void {
    $user = User::factory()->create(['ai_key_verified_at' => now()]);

    expect($user->fresh()->ai_key_verified_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

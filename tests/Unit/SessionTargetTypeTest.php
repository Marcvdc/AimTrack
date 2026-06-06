<?php

use App\Enums\TargetType;
use App\Models\Session;
use App\Models\User;

test('session stores and casts target_type', function () {
    $user = User::factory()->create();

    $session = Session::factory()->create([
        'user_id' => $user->id,
        'target_type' => TargetType::Kkp25m->value,
    ]);

    expect($session->refresh()->target_type)->toBe(TargetType::Kkp25m);
});

test('target_type is nullable', function () {
    $session = Session::factory()->create();

    expect($session->target_type)->toBeNull();
});

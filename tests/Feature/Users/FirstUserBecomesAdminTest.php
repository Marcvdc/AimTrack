<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;

it('maakt de eerste geregistreerde gebruiker automatisch app-beheerder', function (): void {
    $eerste = User::factory()->create(['is_admin' => false]);

    event(new Registered($eerste));

    expect($eerste->fresh()->is_admin)->toBeTrue();
});

it('maakt een latere geregistreerde gebruiker geen beheerder', function (): void {
    User::factory()->create();
    $tweede = User::factory()->create(['is_admin' => false]);

    event(new Registered($tweede));

    expect($tweede->fresh()->is_admin)->toBeFalse();
});

it('promoveert de eerste gebruiker niet nogmaals als die al beheerder is', function (): void {
    $eerste = User::factory()->create(['is_admin' => true]);

    event(new Registered($eerste));

    expect($eerste->fresh()->is_admin)->toBeTrue()
        ->and(User::query()->where('is_admin', true)->count())->toBe(1);
});

<?php

use App\Models\User;
use Illuminate\Support\Carbon;

function runMarkExistingUsersVerifiedMigration(): void
{
    $migration = require database_path('migrations/2026_10_17_083301_mark_existing_users_as_verified.php');
    $migration->up();
}

it('markeert een bestaand ongeverifieerd account als geverifieerd op zijn aanmaakdatum', function (): void {
    $createdAt = Carbon::parse('2026-06-01 12:00:00');
    $user = User::factory()->create(['email_verified_at' => null, 'created_at' => $createdAt]);

    runMarkExistingUsersVerifiedMigration();

    expect($user->fresh()->email_verified_at?->equalTo($createdAt))->toBeTrue();
});

it('laat een al geverifieerd account ongemoeid', function (): void {
    $verifiedAt = Carbon::parse('2026-07-15 08:30:00');
    $user = User::factory()->create(['email_verified_at' => $verifiedAt]);

    runMarkExistingUsersVerifiedMigration();

    expect($user->fresh()->email_verified_at->equalTo($verifiedAt))->toBeTrue();
});

it('valt terug op nu als een account geen aanmaakdatum heeft', function (): void {
    $user = User::factory()->create(['email_verified_at' => null]);
    User::query()->whereKey($user->id)->update(['created_at' => null]);

    runMarkExistingUsersVerifiedMigration();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

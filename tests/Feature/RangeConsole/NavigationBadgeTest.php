<?php

declare(strict_types=1);

use App\Filament\Pages\CoachPage;
use App\Filament\Resources\SessionResource;
use App\Filament\Resources\WeaponResource;
use App\Models\Session;
use App\Models\User;
use App\Models\Weapon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('session navigation badge reflects the acting user session count', function (): void {
    $user = User::factory()->create();
    Session::factory()->count(3)->create(['user_id' => $user->id]);
    // Another user's sessions must not leak into the badge.
    Session::factory()->count(2)->create(['user_id' => User::factory()->create()->id]);

    $this->actingAs($user);

    expect(SessionResource::getNavigationBadge())->toBe('3');
});

test('weapon navigation badge reflects the acting user weapon count', function (): void {
    $user = User::factory()->create();
    Weapon::factory()->count(2)->create(['user_id' => $user->id]);

    $this->actingAs($user);

    expect(WeaponResource::getNavigationBadge())->toBe('2');
});

test('session navigation badge is null when the user has no sessions', function (): void {
    $this->actingAs(User::factory()->create());

    expect(SessionResource::getNavigationBadge())->toBeNull();
});

test('coach page advertises a NEW accent navigation badge', function (): void {
    expect(CoachPage::getNavigationBadge())->toBe('NEW')
        ->and(CoachPage::getNavigationBadgeColor())->toBe('primary');
});

test('the admin panel renders with the regrouped navigation', function (): void {
    $this->actingAs(User::factory()->create());

    $this->get('/admin')->assertSuccessful();
});

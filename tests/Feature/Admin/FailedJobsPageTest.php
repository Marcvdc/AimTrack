<?php

declare(strict_types=1);

use App\Filament\Pages\FailedJobsPage;
use App\Models\User;

it('allows an admin to access the failed-jobs page', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    expect(FailedJobsPage::canAccess())->toBeTrue();
    $this->get(FailedJobsPage::getUrl())->assertSuccessful();
});

it('forbids a non-admin from the failed-jobs page', function (): void {
    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user);

    expect(FailedJobsPage::canAccess())->toBeFalse();
    $this->get(FailedJobsPage::getUrl())->assertForbidden();
});

<?php

use App\Models\User;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    $this->withoutExceptionHandling();
});

it('logs out via POST and flashes a status message', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('filament.admin.auth.logout'));

    $response->assertRedirect('/admin/login');
    $response->assertSessionHas('status', 'Je bent uitgelogd.');
    $this->assertGuest();
});

it('redirects unsigned GET logout requests to a signed URL', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/admin/logout');

    $response->assertRedirect();
    $this->assertStringContainsString('signature=', $response->headers->get('Location'));
    // User should still be authenticated because logout did not run yet.
    $this->assertAuthenticated();
});

it('logs out via the signed GET fallback route', function (): void {
    $user = User::factory()->create();

    $signedUrl = URL::temporarySignedRoute('filament.admin.auth.logout.get', now()->addMinute());

    $response = $this->actingAs($user)->get($signedUrl);

    $response->assertRedirect('/admin/login');
    $response->assertSessionHas('status', 'Je bent uitgelogd.');
    $this->assertGuest();
});

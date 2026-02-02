<?php

use Filament\Facades\Filament;

test('login page shows landing link copy', function (): void {
    $this->get(route('filament.admin.auth.login'))
        ->assertOk()
        ->assertSee('Nog even terugkijken?', escape: false)
        ->assertSee('homepage', escape: false);
});

test('login landing link points to welcome route', function (): void {
    $this->get(route('filament.admin.auth.login'))
        ->assertSee(route('welcome'), escape: false);
});

test('admin user menu registers landing page action', function (): void {
    $actions = Filament::getPanel('admin')->getUserMenuItems();

    expect($actions)->toHaveKey('landing-page');
    expect($actions['landing-page']->getUrl())->toBe(route('welcome'));
});

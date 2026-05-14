<?php

test('admin login page injects aimtrack design tokens', function (): void {
    $response = $this->get('/admin/login');

    $response->assertOk();
    $response->assertSee('<style id="aimtrack-tokens">', escape: false);
    $response->assertSee('--at-bg:        #040711;', escape: false);
    $response->assertSee('--at-accent:    #64f4b3;', escape: false);
});

test('admin login page links the inter and jetbrains mono webfonts', function (): void {
    $response = $this->get('/admin/login');

    $response->assertOk();
    $response->assertSee('fonts.googleapis.com', escape: false);
    $response->assertSee('family=Inter', escape: false);
    $response->assertSee('JetBrains+Mono', escape: false);
});

test('aimtrack tokens stylesheet defines all ten color tokens', function (): void {
    $css = file_get_contents(resource_path('css/aimtrack-tokens.css'));

    expect($css)
        ->toContain('--at-bg:')
        ->toContain('--at-panel:')
        ->toContain('--at-panel-2:')
        ->toContain('--at-line:')
        ->toContain('--at-text:')
        ->toContain('--at-muted:')
        ->toContain('--at-accent:')
        ->toContain('--at-accent-2:')
        ->toContain('--at-warn:')
        ->toContain('--at-cta-text:');
});

test('aimtrack tokens stylesheet defines spacing and radius scales', function (): void {
    $css = file_get_contents(resource_path('css/aimtrack-tokens.css'));

    foreach (['--at-space-1', '--at-space-2', '--at-space-3', '--at-space-4', '--at-space-5', '--at-space-6', '--at-space-7'] as $token) {
        expect($css)->toContain($token);
    }

    foreach (['--at-r-sm', '--at-r-md', '--at-r-lg', '--at-r-xl', '--at-r-2xl', '--at-r-pill'] as $token) {
        expect($css)->toContain($token);
    }
});

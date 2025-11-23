<?php

test('guest is redirected from admin to login route', function () {
    $response = $this->get('/admin');

    $response->assertRedirect(route('login'));
});

test('login route redirects to filament admin login', function () {
    $response = $this->get('/login');

    $response->assertRedirect('/admin/login');
});

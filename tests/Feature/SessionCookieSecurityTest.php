<?php

use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * phpunit.xml zet SESSION_DRIVER=array, en bij die driver voegt StartSession
 * geen sessiecookie aan de response toe. Voor deze tests is dus een persistente
 * driver nodig; 'cookie' vraagt geen database of schrijfbare map.
 */
beforeEach(fn () => config()->set('session.driver', 'cookie'));

function sessionCookie(TestResponse $response): ?Cookie
{
    return collect($response->headers->getCookies())
        ->first(fn (Cookie $cookie): bool => $cookie->getName() === config('session.cookie'));
}

test('sessiecookie krijgt het Secure-attribuut wanneer SESSION_SECURE_COOKIE aanstaat', function () {
    config()->set('session.secure', true);

    $cookie = sessionCookie($this->get('/'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->isHttpOnly())->toBeTrue();
});

test('sessiecookie mist Secure wanneer de vlag uit staat', function () {
    config()->set('session.secure', false);

    $cookie = sessionCookie($this->get('/'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->isSecure())->toBeFalse();
});

test('env-voorbeeld schrijft een secure sessiecookie voor', function () {
    expect(file_get_contents(base_path('.env.example')))
        ->toContain('SESSION_SECURE_COOKIE=true');
});

test('session-config leest de secure-vlag uit de omgeving', function () {
    expect(file_get_contents(config_path('session.php')))
        ->toContain("env('SESSION_SECURE_COOKIE')");
});

test('hardening-checklist eist de secure cookie en dichte poorten', function () {
    $checklist = file_get_contents(base_path('docs/PROD_HARDENING.md'));

    expect($checklist)
        ->toContain('SESSION_SECURE_COOKIE=true')
        ->toContain('rechtstreeks bereikbaar naast de TLS-proxy');
});

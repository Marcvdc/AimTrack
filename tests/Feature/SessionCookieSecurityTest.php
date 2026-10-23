<?php

use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Yaml\Yaml;

beforeEach(function () {
    $this->originalSecureCookieEnv = [
        'server' => $_SERVER['SESSION_SECURE_COOKIE'] ?? null,
        'env' => $_ENV['SESSION_SECURE_COOKIE'] ?? null,
        'putenv' => getenv('SESSION_SECURE_COOKIE'),
    ];
});

afterEach(function () {
    $original = $this->originalSecureCookieEnv;

    unset($_SERVER['SESSION_SECURE_COOKIE'], $_ENV['SESSION_SECURE_COOKIE']);
    putenv('SESSION_SECURE_COOKIE');

    if ($original['server'] !== null) {
        $_SERVER['SESSION_SECURE_COOKIE'] = $original['server'];
    }

    if ($original['env'] !== null) {
        $_ENV['SESSION_SECURE_COOKIE'] = $original['env'];
    }

    if ($original['putenv'] !== false) {
        putenv('SESSION_SECURE_COOKIE='.$original['putenv']);
    }
});

/**
 * Zet SESSION_SECURE_COOKIE zoals een container-environment dat doet, of haalt
 * hem volledig weg, en laadt daarna config/session.php opnieuw. Zo loopt de test
 * door de echte koppeling van env-variabele naar session.secure.
 *
 * phpunit.xml zet SESSION_DRIVER=array, en bij die driver voegt StartSession
 * geen sessiecookie aan de response toe. Daarom de 'cookie'-driver; die vraagt
 * geen database of schrijfbare map.
 */
function loadSessionConfigWithSecureCookieEnv(?string $value): void
{
    unset($_SERVER['SESSION_SECURE_COOKIE'], $_ENV['SESSION_SECURE_COOKIE']);
    putenv('SESSION_SECURE_COOKIE');

    if ($value !== null) {
        $_SERVER['SESSION_SECURE_COOKIE'] = $value;
        $_ENV['SESSION_SECURE_COOKIE'] = $value;
        putenv("SESSION_SECURE_COOKIE={$value}");
    }

    config()->set('session', require config_path('session.php'));
    config()->set('session.driver', 'cookie');
}

/**
 * De requests gaan expliciet over plain HTTP, het scenario uit #123. Over HTTPS
 * zet Symfony bij session.secure = null zelf al Secure, en dan zou een test op
 * een ontbrekende vlag niets zeggen.
 */
function sessionCookie(TestResponse $response): ?Cookie
{
    return collect($response->headers->getCookies())
        ->first(fn (Cookie $cookie): bool => $cookie->getName() === config('session.cookie'));
}

/**
 * @return array<string, array<string, mixed>>
 */
function composeServices(string $path): array
{
    return Yaml::parseFile(base_path($path))['services'];
}

test('sessiecookie krijgt Secure en HttpOnly wanneer SESSION_SECURE_COOKIE=true in de environment staat', function () {
    loadSessionConfigWithSecureCookieEnv('true');

    $cookie = sessionCookie($this->get('http://localhost/'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->isHttpOnly())->toBeTrue();
});

test('sessiecookie mist Secure wanneer SESSION_SECURE_COOKIE ontbreekt of false is', function (?string $value) {
    loadSessionConfigWithSecureCookieEnv($value);

    $cookie = sessionCookie($this->get('http://localhost/'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->isSecure())->toBeFalse();
})->with([
    'variabele ontbreekt' => [null],
    'variabele is false' => ['false'],
]);

test('env-voorbeeld schrijft een secure sessiecookie voor', function () {
    expect(file_get_contents(base_path('.env.example')))
        ->toContain('SESSION_SECURE_COOKIE=true');
});

test('compose.prod.yml geeft elke container die de config-cache deelt dezelfde environment met de vlag aan', function () {
    $services = composeServices('docker/compose.prod.yml');

    $sharingBootstrapCache = collect($services)->filter(
        fn (array $service): bool => collect($service['volumes'] ?? [])
            ->contains(fn (string $volume): bool => str_starts_with($volume, 'app_bootstrap_cache:'))
    );

    expect($sharingBootstrapCache->keys()->all())->toContain('app', 'queue');

    $sharingBootstrapCache->each(function (array $service, string $name): void {
        expect($service['environment']['SESSION_SECURE_COOKIE'] ?? null)
            ->toBe('true', "Service {$name} deelt de config-cache maar zet SESSION_SECURE_COOKIE niet op true");
    });

    expect($sharingBootstrapCache->pluck('environment')->unique()->count())->toBe(1)
        ->and($sharingBootstrapCache->pluck('env_file')->unique()->count())->toBe(1);
});

test('root docker-compose.yml zet de vlag standaard aan voor app en queue', function (string $service) {
    expect(composeServices('docker-compose.yml')[$service]['environment']['SESSION_SECURE_COOKIE'] ?? null)
        ->toBe('${SESSION_SECURE_COOKIE:-true}');
})->with(['app', 'queue']);

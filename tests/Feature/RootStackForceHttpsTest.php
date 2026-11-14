<?php

use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Yaml\Yaml;

beforeEach(function () {
    $this->originalForceHttpsEnv = [
        'server' => $_SERVER['APP_FORCE_HTTPS'] ?? null,
        'env' => $_ENV['APP_FORCE_HTTPS'] ?? null,
        'putenv' => getenv('APP_FORCE_HTTPS'),
    ];
    $this->originalForceHttpsConfig = config('app.force_https');
});

afterEach(function () {
    $original = $this->originalForceHttpsEnv;

    unset($_SERVER['APP_FORCE_HTTPS'], $_ENV['APP_FORCE_HTTPS']);
    putenv('APP_FORCE_HTTPS');

    if ($original['server'] !== null) {
        $_SERVER['APP_FORCE_HTTPS'] = $original['server'];
    }

    if ($original['env'] !== null) {
        $_ENV['APP_FORCE_HTTPS'] = $original['env'];
    }

    if ($original['putenv'] !== false) {
        putenv('APP_FORCE_HTTPS='.$original['putenv']);
    }

    config()->set('app.force_https', $this->originalForceHttpsConfig);
    URL::forceScheme(null);
});

/**
 * Zet APP_FORCE_HTTPS zoals de root-stack dat als procesvariabele doet, laadt
 * config/app.php opnieuw en boot de AppServiceProvider. Zo loopt de test door de
 * echte keten van container-environment naar het schema van gegenereerde URL's,
 * waaronder het update-endpoint van Livewire.
 *
 * De URL-generator krijgt een request over plain HTTP, zoals de browser op
 * http://localhost:18080 dat stuurt. Anders volgt het schema APP_URL uit de
 * omgeving (in CI https, via de gekopieerde .env.example) en zegt de test niets.
 */
function bootWithForceHttpsEnv(string $value): void
{
    $_SERVER['APP_FORCE_HTTPS'] = $value;
    $_ENV['APP_FORCE_HTTPS'] = $value;
    putenv("APP_FORCE_HTTPS={$value}");

    config()->set('app.force_https', (require config_path('app.php'))['force_https']);
    URL::forceScheme(null);
    app('url')->setRequest(Request::create('http://localhost:18080/admin/login'));

    (new AppServiceProvider(app()))->boot();
}

/**
 * @return array<string, mixed>
 */
function rootStackEnvironment(string $service): array
{
    return Yaml::parseFile(base_path('docker-compose.yml'))['services'][$service]['environment'];
}

test('root docker-compose.yml forceert https standaard voor app en queue', function (string $service) {
    expect(rootStackEnvironment($service)['APP_FORCE_HTTPS'] ?? null)
        ->toBe('${STACK_FORCE_HTTPS:-true}');
})->with(['app', 'queue']);

test('env-voorbeeld zet de stack-schakelaar niet, zodat een gekopieerde .env https niet uitzet', function () {
    expect(file_get_contents(base_path('.env.example')))
        ->not->toMatch('/^\s*STACK_FORCE_HTTPS\s*=/m');
});

test('APP_FORCE_HTTPS uit de container-environment bepaalt het schema van gegenereerde URLs', function (string $value, string $scheme) {
    bootWithForceHttpsEnv($value);

    expect(parse_url(url('/livewire/update'), PHP_URL_SCHEME))->toBe($scheme);
})->with([
    'stack-default true' => ['true', 'https'],
    'lokaal uitgezet' => ['false', 'http'],
]);

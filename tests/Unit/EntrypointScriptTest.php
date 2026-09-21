<?php

use Illuminate\Support\Facades\Process;

/**
 * Bewaakt de storage-voorbereiding in docker/entrypoint.sh (PR #119).
 *
 * Het script draait echt, met APP_ROOT op een tijdelijke map en een gestubde
 * `php`, `chown`, `rsync` en `flock` op de PATH. De chown-stub legt vast welke
 * disk-roots er bestaan op het moment dat hij wordt aangeroepen, zodat "de
 * mappen staan er vóór de chown" een waarneembaar feit is.
 */

/**
 * @param  list<string>  $lines
 */
function writeEntrypointStub(string $path, array $lines): void
{
    file_put_contents($path, "#!/usr/bin/env bash\n".implode("\n", $lines)."\n");
    chmod($path, 0755);
}

/**
 * @return array{root: string, app: string, env: array<string, string>}
 */
function entrypointSandbox(): array
{
    $root = sys_get_temp_dir().'/aimtrack-entrypoint-'.bin2hex(random_bytes(6));
    $app = $root.'/app';

    mkdir($root.'/bin', 0755, true);
    mkdir($app.'/storage', 0755, true);
    mkdir($app.'/bootstrap', 0755, true);

    writeEntrypointStub($root.'/bin/chown', [
        'for dir in storage/app/private storage/app/public; do',
        '  if [ -d "${dir}" ]; then echo "${dir}=aanwezig"; else echo "${dir}=ontbreekt"; fi',
        'done >> "${CHOWN_LOG}"',
        'echo "chown $*" >> "${CHOWN_LOG}"',
        'exit 0',
    ]);

    foreach (['php', 'rsync', 'flock'] as $command) {
        writeEntrypointStub($root.'/bin/'.$command, ['exit 0']);
    }

    return [
        'root' => $root,
        'app' => $app,
        'env' => [
            'PATH' => $root.'/bin:'.getenv('PATH'),
            'APP_ROOT' => $app,
            'APP_ENV' => 'production',
            'CHOWN_LOG' => $root.'/chown.log',
        ],
    ];
}

function removeEntrypointSandbox(string $root): void
{
    exec('chmod -R u+w '.escapeshellarg($root).' 2>/dev/null; rm -rf '.escapeshellarg($root));
}

test('entrypoint is syntactisch geldig onder bash', function () {
    $result = Process::run(['bash', '-n', base_path('docker/entrypoint.sh')]);

    expect($result->exitCode())->toBe(0, $result->errorOutput());
});

test('entrypoint maakt de disk-roots aan voordat de rechten worden gezet', function () {
    ['root' => $root, 'app' => $app, 'env' => $env] = entrypointSandbox();

    $result = Process::env($env)
        ->run(['bash', base_path('docker/entrypoint.sh'), 'touch', $root.'/exec-ran']);

    expect($result->exitCode())->toBe(0, $result->errorOutput())
        ->and(is_dir($app.'/storage/app/private'))->toBeTrue()
        ->and(is_dir($app.'/storage/app/public'))->toBeTrue()
        ->and(file_get_contents($root.'/chown.log'))
        ->toContain("storage/app/private=aanwezig\nstorage/app/public=aanwezig\nchown -R www-data:www-data storage bootstrap/cache")
        ->and(file_exists($root.'/exec-ran'))->toBeTrue();

    removeEntrypointSandbox($root);
});

test('entrypoint laat bestaande bestanden in de disk-roots staan', function () {
    ['root' => $root, 'app' => $app, 'env' => $env] = entrypointSandbox();
    mkdir($app.'/storage/app/private/bijlagen', 0755, true);
    file_put_contents($app.'/storage/app/private/bijlagen/schietkaart.jpg', 'bestaand');

    $result = Process::env($env)
        ->run(['bash', base_path('docker/entrypoint.sh'), 'true']);

    expect($result->exitCode())->toBe(0, $result->errorOutput())
        ->and(file_get_contents($app.'/storage/app/private/bijlagen/schietkaart.jpg'))->toBe('bestaand');

    removeEntrypointSandbox($root);
});

test('entrypoint faalt bij boot als storage/app niet schrijfbaar is', function () {
    if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
        test()->markTestSkipped('Als root negeert het bestandssysteem de schrijfrechten.');
    }

    ['root' => $root, 'app' => $app, 'env' => $env] = entrypointSandbox();
    mkdir($app.'/storage/app', 0755);
    chmod($app.'/storage/app', 0555);

    $result = Process::env($env)
        ->run(['bash', base_path('docker/entrypoint.sh'), 'touch', $root.'/exec-ran']);

    expect($result->exitCode())->not->toBe(0)
        ->and($result->errorOutput())->toContain('storage/app/private')
        ->and(file_exists($root.'/exec-ran'))->toBeFalse()
        ->and(file_exists($root.'/chown.log'))->toBeFalse();

    removeEntrypointSandbox($root);
});

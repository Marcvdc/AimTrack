<?php

/**
 * Bewaakt dat de backup-container draait op het Debian-image (php:8.4-fpm-bookworm).
 *
 * Drie regressies uit issue #109 worden hier afgedekt:
 *  1. dash-crash — de scripts moeten onder bash draaien (set -o pipefail kent dash niet).
 *  2. Alpine-isms — geen crond/etc/crontabs; wel Debian's cron + /etc/cron.d/.
 *  3. env-propagatie — Debian cron geeft de container-env niet door, dus de
 *     entrypoint moet DB-credentials/paden in het cron.d-bestand snapshotten.
 */
test('backup scripts gebruiken bash en geen sh shebang', function (string $script) {
    $contents = file_get_contents(base_path($script));

    expect($contents)
        ->toStartWith("#!/usr/bin/env bash\n")
        ->not->toContain('#!/usr/bin/env sh');
})->with([
    'docker/backup/entrypoint.sh',
    'docker/backup/run-backup.sh',
]);

test('backup scripts zijn syntactisch geldig onder bash', function (string $script) {
    $path = escapeshellarg(base_path($script));

    exec("bash -n {$path} 2>&1", $output, $exitCode);

    expect($exitCode)->toBe(0, implode("\n", $output));
})->with([
    'docker/backup/entrypoint.sh',
    'docker/backup/run-backup.sh',
]);

test('entrypoint gebruikt Debian cron en geen Alpine crond', function () {
    $entrypoint = file_get_contents(base_path('docker/backup/entrypoint.sh'));

    expect($entrypoint)
        ->toContain('exec cron -f')
        ->toContain('/etc/cron.d/')
        ->not->toContain('exec crond')
        ->not->toContain('/etc/crontabs/root');
});

test('cron-job-regel bevat het verplichte Debian user-veld', function () {
    $entrypoint = file_get_contents(base_path('docker/backup/entrypoint.sh'));

    expect($entrypoint)->toContain('${CRON_SCHEDULE} root ${BACKUP_RUNNER_TARGET}');
});

test('entrypoint snapshot de backup- en db-variabelen naar het cron-bestand', function (string $variable) {
    $entrypoint = file_get_contents(base_path('docker/backup/entrypoint.sh'));

    expect($entrypoint)->toContain($variable);
})->with([
    'BACKUP_MODE',
    'BACKUP_DIR',
    'BACKUP_PREFIX',
    'RETENTION_DAYS',
    'DB_HOST',
    'DB_PORT',
    'DB_DATABASE',
    'DB_USERNAME',
    'DB_PASSWORD',
]);

test('runner draait standaard de productie-dump via direct mode', function () {
    $runner = file_get_contents(base_path('docker/backup/run-backup.sh'));

    expect($runner)
        ->toContain('scripts/backup-dev-db.sh')
        ->toContain('BACKUP_MODE');
});

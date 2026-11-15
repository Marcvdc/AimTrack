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

/**
 * De Pi-scripts (issue #190) zijn vervangen door de backup-dienst hierboven.
 * Ze mogen niet terugkomen en de docs mogen er niet meer naar verwijzen,
 * anders volgt iemand bij een herstel een pad dat niet meer bestaat.
 */
test('de vervallen Pi-backupscripts bestaan niet meer', function (string $script) {
    expect(file_exists(base_path("scripts/{$script}")))->toBeFalse();
})->with([
    'backup-aimtrack.sh',
    'restore-test-aimtrack.sh',
]);

test('docs, scripts en docker noemen de vervallen Pi-backupscripts niet meer', function () {
    $vervallen = ['backup-aimtrack.sh', 'restore-test-aimtrack.sh'];
    $bestanden = [base_path('Dockerfile'), base_path('README.md')];

    foreach (['docs', 'scripts', 'docker'] as $map) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(base_path($map), FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $bestand) {
            if ($bestand->isFile()) {
                $bestanden[] = $bestand->getPathname();
            }
        }
    }

    $treffers = [];

    foreach ($bestanden as $pad) {
        $inhoud = file_get_contents($pad);

        foreach ($vervallen as $script) {
            if (str_contains($inhoud, $script)) {
                $treffers[] = str_replace(base_path().'/', '', $pad)." noemt {$script}";
            }
        }
    }

    expect($treffers)->toBe([]);
});

test('het dev-backupscript blijft bestaan en wordt in de image uitvoerbaar gemaakt', function () {
    expect(file_exists(base_path('scripts/backup-dev-db.sh')))->toBeTrue()
        ->and(file_get_contents(base_path('Dockerfile')))->toContain('scripts/backup-dev-db.sh');
});

<?php

use Illuminate\Support\Facades\Process;

/**
 * Bewaakt het terugvalpad van de deploy (issue #137).
 *
 * De scripts worden echt uitgevoerd in een tijdelijke DEPLOY_PATH, met een
 * gestubde `docker` en `curl` op de PATH. De docker-stub onthoudt op welke tag
 * de stack voor het laatst is gestart; de curl-stub laat de healthcheck falen
 * voor precies die ene tag. Zo is "de kapotte versie is vervangen door de
 * vorige" een waarneembaar feit en niet alleen een regel tekst in het script.
 */

/**
 * @param  list<string>  $lines
 */
function writeStub(string $path, array $lines): void
{
    file_put_contents($path, "#!/usr/bin/env bash\n".implode("\n", $lines)."\n");
    chmod($path, 0755);
}

/**
 * @return array{root: string, env: array<string, string>}
 */
function deploySandbox(array $state = []): array
{
    $root = sys_get_temp_dir().'/aimtrack-deploy-'.bin2hex(random_bytes(6));

    mkdir($root.'/scripts', 0755, true);
    mkdir($root.'/bin', 0755, true);
    mkdir($root.'/docker', 0755, true);

    foreach (['remote_deploy.sh', 'rollback.sh', 'migrate.sh', 'healthcheck.sh'] as $script) {
        copy(base_path('scripts/'.$script), $root.'/scripts/'.$script);
        chmod($root.'/scripts/'.$script, 0755);
    }

    file_put_contents($root.'/.env', "APP_ENV=production\n");
    touch($root.'/docker/compose.prod.yml');

    if ($state !== []) {
        mkdir($root.'/.deploy', 0755, true);
        foreach ($state as $file => $contents) {
            file_put_contents($root.'/.deploy/'.$file, $contents."\n");
        }
    }

    writeStub($root.'/bin/docker', [
        'echo "$*" >> "${DOCKER_CALL_LOG}"',
        'case "$*" in',
        '  *"up -d"*) printf \'%s\' "${IMAGE_TAG:-}" > "${RUNNING_TAG_FILE}" ;;',
        'esac',
        'if [ -n "${DOCKER_FAIL_ON:-}" ] && [[ "$*" == *"${DOCKER_FAIL_ON}"* ]]; then',
        '  echo "stub docker: forced failure on $*" >&2',
        '  exit 1',
        'fi',
        'exit 0',
    ]);

    writeStub($root.'/bin/curl', [
        'running=""',
        'if [ -f "${RUNNING_TAG_FILE}" ]; then',
        '  running="$(cat "${RUNNING_TAG_FILE}")"',
        'fi',
        'if [ -n "${CURL_FAIL_TAG:-}" ] && [ "${running}" = "${CURL_FAIL_TAG}" ]; then',
        '  exit 1',
        'fi',
        'exit 0',
    ]);

    return [
        'root' => $root,
        'env' => [
            'PATH' => $root.'/bin:'.getenv('PATH'),
            'DEPLOY_PATH' => $root,
            'REGISTRY_IMAGE' => 'ghcr.io/marcvdc/aimtrack',
            'COMPOSE_FILE' => 'docker/compose.prod.yml',
            'APP_URL' => 'https://aimtrack.test',
            'DOCKER_CALL_LOG' => $root.'/docker-calls.log',
            'RUNNING_TAG_FILE' => $root.'/running-tag',
            'TIMEOUT' => '1',
            'SLEEP' => '1',
        ],
    ];
}

function removeSandbox(string $root): void
{
    exec('rm -rf '.escapeshellarg($root));
}

function stateValue(string $root, string $file): ?string
{
    $path = $root.'/.deploy/'.$file;

    return file_exists($path) ? trim(file_get_contents($path)) : null;
}

function runningTag(string $root): ?string
{
    $path = $root.'/running-tag';

    return file_exists($path) ? trim(file_get_contents($path)) : null;
}

test('deployscripts zijn syntactisch geldig onder bash', function (string $script) {
    $result = Process::run(['bash', '-n', base_path($script)]);

    expect($result->exitCode())->toBe(0, $result->errorOutput());
})->with([
    'scripts/remote_deploy.sh',
    'scripts/rollback.sh',
    'scripts/migrate.sh',
    'scripts/healthcheck.sh',
]);

test('deployscripts gebruiken de bash shebang', function (string $script) {
    expect(file_get_contents(base_path($script)))->toStartWith("#!/usr/bin/env bash\n");
})->with([
    'scripts/remote_deploy.sh',
    'scripts/rollback.sh',
]);

test('een geslaagde deploy schuift de vorige tag door naar previous_successful_tag', function () {
    ['root' => $root, 'env' => $env] = deploySandbox(['last_successful_tag' => 'oud111']);

    $result = Process::path($root)
        ->env($env + ['IMAGE_TAG' => 'nieuw22'])
        ->run('bash scripts/remote_deploy.sh');

    expect($result->exitCode())->toBe(0, $result->errorOutput())
        ->and(stateValue($root, 'last_successful_tag'))->toBe('nieuw22')
        ->and(stateValue($root, 'previous_successful_tag'))->toBe('oud111')
        ->and(runningTag($root))->toBe('nieuw22')
        ->and(stateValue($root, 'deploy_history'))->toContain("deploy\tnieuw22\tok");

    removeSandbox($root);
});

test('de eerste deploy legt alleen de huidige tag vast', function () {
    ['root' => $root, 'env' => $env] = deploySandbox();

    $result = Process::path($root)
        ->env($env + ['IMAGE_TAG' => 'eerste1'])
        ->run('bash scripts/remote_deploy.sh');

    expect($result->exitCode())->toBe(0, $result->errorOutput())
        ->and(stateValue($root, 'last_successful_tag'))->toBe('eerste1')
        ->and(stateValue($root, 'previous_successful_tag'))->toBeNull();

    removeSandbox($root);
});

test('het statusbestand landt in DEPLOY_PATH en niet in de cwd van de aanroeper', function () {
    ['root' => $root, 'env' => $env] = deploySandbox();
    $elders = sys_get_temp_dir().'/aimtrack-cwd-'.bin2hex(random_bytes(6));
    mkdir($elders, 0755, true);

    $result = Process::path($elders)
        ->env($env + ['IMAGE_TAG' => 'vanafelders'])
        ->run('bash '.escapeshellarg($root.'/scripts/remote_deploy.sh'));

    expect($result->exitCode())->toBe(0, $result->errorOutput())
        ->and(stateValue($root, 'last_successful_tag'))->toBe('vanafelders')
        ->and(is_dir($elders.'/.deploy'))->toBeFalse();

    removeSandbox($root);
    removeSandbox($elders);
});

test('een falende healthcheck zet de vorige tag terug', function () {
    ['root' => $root, 'env' => $env] = deploySandbox(['last_successful_tag' => 'oud111']);

    $result = Process::path($root)
        ->env($env + ['IMAGE_TAG' => 'kapot99', 'CURL_FAIL_TAG' => 'kapot99'])
        ->run('bash scripts/remote_deploy.sh');

    expect($result->exitCode())->toBe(20, $result->errorOutput())
        ->and(runningTag($root))->toBe('oud111')
        ->and(stateValue($root, 'last_successful_tag'))->toBe('oud111')
        ->and(stateValue($root, 'deploy_history'))->toContain("rollback\toud111\tauto:")
        ->and($result->errorOutput())->toContain('Rolled back to oud111');

    removeSandbox($root);
});

test('een falende migratie zet de vorige tag terug', function () {
    ['root' => $root, 'env' => $env] = deploySandbox(['last_successful_tag' => 'oud111']);

    $result = Process::path($root)
        ->env($env + ['IMAGE_TAG' => 'kapot99', 'DOCKER_FAIL_ON' => 'exec -T app'])
        ->run('bash scripts/remote_deploy.sh');

    expect($result->exitCode())->toBe(20, $result->errorOutput())
        ->and(runningTag($root))->toBe('oud111')
        ->and(stateValue($root, 'last_successful_tag'))->toBe('oud111')
        ->and($result->errorOutput())->toContain('database migrations failed');

    removeSandbox($root);
});

test('zonder bekende vorige tag stopt de deploy met een eigen exitcode', function () {
    ['root' => $root, 'env' => $env] = deploySandbox();

    $result = Process::path($root)
        ->env($env + ['IMAGE_TAG' => 'kapot99', 'CURL_FAIL_TAG' => 'kapot99'])
        ->run('bash scripts/remote_deploy.sh');

    expect($result->exitCode())->toBe(21, $result->errorOutput())
        ->and($result->errorOutput())->toContain('cannot roll back automatically')
        ->and(stateValue($root, 'last_successful_tag'))->toBeNull();

    removeSandbox($root);
});

test('AUTO_ROLLBACK=false laat de nieuwe tag staan', function () {
    ['root' => $root, 'env' => $env] = deploySandbox(['last_successful_tag' => 'oud111']);

    $result = Process::path($root)
        ->env($env + ['IMAGE_TAG' => 'kapot99', 'CURL_FAIL_TAG' => 'kapot99', 'AUTO_ROLLBACK' => 'false'])
        ->run('bash scripts/remote_deploy.sh');

    expect($result->exitCode())->toBe(21, $result->errorOutput())
        ->and(runningTag($root))->toBe('kapot99')
        ->and($result->errorOutput())->toContain('AUTO_ROLLBACK is disabled');

    removeSandbox($root);
});

test('rollback.sh zonder argument pakt previous_successful_tag', function () {
    ['root' => $root, 'env' => $env] = deploySandbox([
        'last_successful_tag' => 'kapot99',
        'previous_successful_tag' => 'oud111',
        'registry_image' => 'ghcr.io/marcvdc/aimtrack',
        'compose_file' => 'docker/compose.prod.yml',
    ]);

    $result = Process::path($root)
        ->env($env)
        ->run('bash scripts/rollback.sh');

    expect($result->exitCode())->toBe(0, $result->errorOutput())
        ->and(runningTag($root))->toBe('oud111')
        ->and(stateValue($root, 'last_successful_tag'))->toBe('oud111')
        ->and(stateValue($root, 'previous_successful_tag'))->toBe('oud111')
        ->and(stateValue($root, 'deploy_history'))->toContain("rollback\toud111\tmanual");

    removeSandbox($root);
});

test('rollback.sh draait geen migraties', function () {
    ['root' => $root, 'env' => $env] = deploySandbox([
        'last_successful_tag' => 'kapot99',
        'previous_successful_tag' => 'oud111',
        'registry_image' => 'ghcr.io/marcvdc/aimtrack',
        'compose_file' => 'docker/compose.prod.yml',
    ]);

    Process::path($root)->env($env)->run('bash scripts/rollback.sh');

    expect(file_get_contents($root.'/docker-calls.log'))->not->toContain('artisan migrate');

    removeSandbox($root);
});

test('rollback.sh accepteert een expliciete tag', function () {
    ['root' => $root, 'env' => $env] = deploySandbox([
        'last_successful_tag' => 'kapot99',
        'previous_successful_tag' => 'oud111',
        'registry_image' => 'ghcr.io/marcvdc/aimtrack',
        'compose_file' => 'docker/compose.prod.yml',
    ]);

    $result = Process::path($root)
        ->env($env)
        ->run('bash scripts/rollback.sh ouder00');

    expect($result->exitCode())->toBe(0, $result->errorOutput())
        ->and(runningTag($root))->toBe('ouder00')
        ->and(stateValue($root, 'last_successful_tag'))->toBe('ouder00');

    removeSandbox($root);
});

test('rollback.sh valt niet terug op de staging-compose als het compose-bestand onbekend is', function () {
    ['root' => $root, 'env' => $env] = deploySandbox([
        'last_successful_tag' => 'kapot99',
        'previous_successful_tag' => 'oud111',
        'registry_image' => 'ghcr.io/marcvdc/aimtrack',
    ]);
    unset($env['COMPOSE_FILE']);

    $result = Process::path($root)
        ->env($env)
        ->run('bash scripts/rollback.sh');

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('COMPOSE_FILE is niet bekend')
        ->and(runningTag($root))->toBeNull();

    removeSandbox($root);
});

test('rollback.sh zonder tag en zonder historie weigert te raden', function () {
    ['root' => $root, 'env' => $env] = deploySandbox();
    unset($env['REGISTRY_IMAGE']);

    $result = Process::path($root)
        ->env($env)
        ->run('bash scripts/rollback.sh');

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())->toContain('Geen tag opgegeven');

    removeSandbox($root);
});

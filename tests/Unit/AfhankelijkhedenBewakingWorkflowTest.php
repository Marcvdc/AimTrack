<?php

use Symfony\Component\Yaml\Yaml;

/**
 * Bewaakt dat afhankelijkheden zichtbaar en afgedwongen bijgewerkt worden (issue #189, deel van #136).
 *
 * Dependabot opent wekelijks update-PR's voor composer, npm en de GitHub Actions zelf, en de
 * composer audit in CI maakt de run rood zodra er een advisory op composer.lock staat. Die audit
 * mocht falen zolang er nog advisories openstonden; sinds #179 is die set leeg.
 */

/**
 * @return array<string, mixed>
 */
function geparsteYaml(string $relatiefPad): array
{
    $pad = base_path($relatiefPad);

    expect($pad)->toBeFile();

    return Yaml::parseFile($pad);
}

/**
 * @return array<string, mixed>
 */
function auditJob(): array
{
    $workflow = geparsteYaml('.github/workflows/ci.yml');

    expect($workflow['jobs'] ?? [])->toHaveKey('audit');

    return $workflow['jobs']['audit'];
}

/**
 * @return array<string, mixed>
 */
function composerAuditStap(): array
{
    foreach (auditJob()['steps'] ?? [] as $stap) {
        if (str_contains((string) ($stap['run'] ?? ''), 'composer audit')) {
            return $stap;
        }
    }

    throw new RuntimeException('Geen stap met composer audit gevonden in de audit-job van ci.yml.');
}

it('draait composer audit in ci', function () {
    expect(composerAuditStap()['run'])->toContain('composer audit --locked');
});

it('laat de composer audit falen bij een advisory', function () {
    $stap = composerAuditStap();

    expect($stap)->not->toHaveKey('continue-on-error')
        ->and(auditJob())->not->toHaveKey('continue-on-error')
        ->and($stap['run'])->toContain('status=${PIPESTATUS[0]}')
        ->and($stap['run'])->not->toContain('status=$?')
        ->and($stap['run'])->toContain('exit "${status}"')
        ->and($stap['run'])->toContain('$GITHUB_STEP_SUMMARY');
});

it('meldt een gefaalde audit ook echt, met failure() in de conditie', function () {
    $rapportage = collect(auditJob()['steps'] ?? [])
        ->firstWhere('name', 'Report audit outcome');

    expect($rapportage)->not->toBeNull()
        ->and((string) ($rapportage['if'] ?? ''))->toContain('failure()');
});

it('heeft een dependabot-configuratie voor composer, npm en github-actions', function () {
    $config = geparsteYaml('.github/dependabot.yml');

    $ecosystemen = array_column($config['updates'] ?? [], 'package-ecosystem');

    expect($config['version'] ?? null)->toBe(2)
        ->and($ecosystemen)->toContain('composer', 'npm', 'github-actions');
});

it('werkt elk ecosysteem wekelijks bij, gegroepeerd en met een limiet op open PRs', function () {
    $config = geparsteYaml('.github/dependabot.yml');

    expect($config['updates'] ?? [])->not->toBeEmpty();

    foreach ($config['updates'] as $update) {
        $naam = $update['package-ecosystem'] ?? 'onbekend';

        expect($update['schedule']['interval'] ?? null)->toBe('weekly', "{$naam} draait niet wekelijks")
            ->and($update['open-pull-requests-limit'] ?? null)->toBeInt("{$naam} heeft geen PR-limiet")
            ->and($update['open-pull-requests-limit'] ?? 0)->toBeGreaterThan(0, "{$naam} heeft PR-limiet 0, dan staan de updates uit");

        $updateTypes = collect($update['groups'] ?? [])
            ->flatMap(fn (array $groep): array => $groep['update-types'] ?? [])
            ->all();

        expect(in_array('minor', $updateTypes, true) && in_array('patch', $updateTypes, true))
            ->toBeTrue("{$naam} groepeert minor en patch niet")
            ->and(in_array('major', $updateTypes, true))
            ->toBeFalse("{$naam} groepeert ook major-updates");
    }
});

/*
 * Composer en Dependabot rekenen met config.platform.php uit composer.json (#212). Zonder die pin
 * nam Dependabot de ondergrens van require.php (8.4.0) en loste geen enkele update op, omdat
 * symfony-pakketten in de lock minstens 8.4.1 vragen.
 */

function phpPin(): string
{
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    return (string) ($composer['config']['platform']['php'] ?? '');
}

function minor(string $versie): string
{
    return implode('.', array_slice(explode('.', $versie), 0, 2));
}

it('pint de PHP-versie voor composer op een volledige patchversie', function () {
    expect(phpPin())->toMatch('/^\d+\.\d+\.\d+$/');
});

it('zet geen ext-* in config.platform, zodat composer de extensies echt controleert', function () {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    $extensies = array_filter(
        array_keys($composer['config']['platform'] ?? []),
        fn (string $pakket): bool => str_starts_with($pakket, 'ext-'),
    );

    expect($extensies)->toBe([]);
});

it('houdt de PHP-pin op dezelfde minor als de CI en de Dockerfile', function () {
    $minor = minor(phpPin());

    $ci = (string) file_get_contents(base_path('.github/workflows/ci.yml'));
    preg_match_all("/php-version: '([^']+)'/", $ci, $setupPhp);
    preg_match_all('/PHP_VERSION=(\d+\.\d+)/', $ci, $buildArgs);
    preg_match('/^ARG PHP_VERSION=(\S+)$/m', (string) file_get_contents(base_path('Dockerfile')), $dockerfile);

    $versies = [...$setupPhp[1], ...$buildArgs[1], $dockerfile[1] ?? 'ontbreekt'];

    expect($setupPhp[1])->not->toBeEmpty();

    foreach ($versies as $versie) {
        expect(minor($versie))->toBe($minor, "PHP {$versie} in CI of Dockerfile past niet bij platform.php ".phpPin());
    }
});

it('controleert de PHP-pin in de lint-job, die required is', function () {
    $workflow = geparsteYaml('.github/workflows/ci.yml');

    $stap = collect($workflow['jobs']['lint']['steps'] ?? [])
        ->firstWhere('name', 'Check PHP pin in composer.json');

    expect($stap)->not->toBeNull()
        ->and($stap)->not->toHaveKey('continue-on-error')
        ->and((string) ($stap['run'] ?? ''))->toContain('composer config platform.php');
});

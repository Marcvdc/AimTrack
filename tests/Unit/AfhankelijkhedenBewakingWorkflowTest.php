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
function composerAuditStap(): array
{
    $workflow = geparsteYaml('.github/workflows/ci.yml');

    foreach ($workflow['jobs'] ?? [] as $job) {
        foreach ($job['steps'] ?? [] as $stap) {
            if (str_contains((string) ($stap['run'] ?? ''), 'composer audit')) {
                return $stap;
            }
        }
    }

    throw new RuntimeException('Geen stap met composer audit gevonden in ci.yml.');
}

it('draait composer audit in ci', function () {
    expect(composerAuditStap()['run'])->toContain('composer audit --locked');
});

it('laat de composer audit falen bij een advisory', function () {
    $stap = composerAuditStap();

    expect($stap)->not->toHaveKey('continue-on-error')
        ->and($stap['run'])->toContain('exit "${status}"')
        ->and($stap['run'])->toContain('$GITHUB_STEP_SUMMARY');
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
            ->and($update['open-pull-requests-limit'] ?? null)->toBeInt("{$naam} heeft geen PR-limiet");

        $updateTypes = collect($update['groups'] ?? [])
            ->flatMap(fn (array $groep): array => $groep['update-types'] ?? [])
            ->all();

        expect($updateTypes)->toContain('minor', 'patch');
    }
});

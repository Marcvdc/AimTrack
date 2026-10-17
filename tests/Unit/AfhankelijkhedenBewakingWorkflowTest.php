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

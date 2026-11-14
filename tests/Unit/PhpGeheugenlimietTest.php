<?php

use Symfony\Component\Yaml\Yaml;

/**
 * Bewaakt de geheugenlimiet van PHP per omgeving (issue #167).
 *
 * Het dev-image krijgt een ruimere memory_limit zodat de testsuite in de container draait.
 * Die limiet mag niet ongemerkt in de production-image belanden: daar blijft de standaard van
 * php.ini-production (128M) staan. De CI meet met dezelfde grens als het dev-image.
 */

/**
 * @return array<string, string>
 */
function dockerfileStagesVoorGeheugen(): array
{
    $stages = [];
    $huidige = null;

    foreach (file(base_path('Dockerfile'), FILE_IGNORE_NEW_LINES) as $regel) {
        if (preg_match('/^FROM\s+\S+\s+AS\s+(\S+)/i', $regel, $match) === 1) {
            $huidige = strtolower($match[1]);
            $stages[$huidige] = '';

            continue;
        }

        if ($huidige !== null) {
            $stages[$huidige] .= $regel."\n";
        }
    }

    return $stages;
}

function devGeheugenlimiet(): string
{
    $ini = parse_ini_file(base_path('docker/php/dev-memory.ini'));

    expect($ini)->toBeArray()->toHaveKey('memory_limit');

    return (string) $ini['memory_limit'];
}

test('het dev-ini-bestand zet een expliciete ruime limiet en geen onbeperkte', function () {
    $limiet = devGeheugenlimiet();

    expect($limiet)->not->toBe('-1')
        ->and($limiet)->toMatch('/^\d+[MG]$/');

    $inMegabytes = str_ends_with($limiet, 'G')
        ? (int) $limiet * 1024
        : (int) $limiet;

    expect($inMegabytes)->toBeGreaterThan(128);
});

test('alleen het dev-target kopieert het geheugen-ini-bestand', function () {
    $stages = dockerfileStagesVoorGeheugen();

    expect($stages)->toHaveKeys(['base', 'dev', 'production'])
        ->and($stages['dev'])->toContain('docker/php/dev-memory.ini')
        ->and($stages['base'])->not->toContain('dev-memory.ini')
        ->and($stages['production'])->not->toContain('dev-memory.ini')
        ->and($stages['production'])->not->toContain('memory_limit');
});

test('de production-image raakt memory_limit niet aan via zijn eigen ini-bestanden', function () {
    $bestanden = ['docker/php/hardening.ini', 'docker/php/uploads.ini', 'docker/php/fpm-hardening.conf'];

    foreach ($bestanden as $bestand) {
        $actief = array_filter(
            file(base_path($bestand), FILE_IGNORE_NEW_LINES),
            fn (string $regel): bool => ! str_starts_with(ltrim($regel), ';'),
        );

        expect(implode("\n", $actief))->not->toContain('memory_limit', "{$bestand} zet memory_limit");
    }
});

test('de testjob in de CI zet dezelfde geheugenlimiet als het dev-image', function () {
    $workflow = Yaml::parseFile(base_path('.github/workflows/ci.yml'));

    $setupPhp = collect($workflow['jobs']['test']['steps'])
        ->first(fn (array $stap): bool => str_starts_with($stap['uses'] ?? '', 'shivammathur/setup-php'));

    expect($setupPhp)->not->toBeNull()
        ->and($setupPhp['with']['ini-values'] ?? '')->toContain('memory_limit='.devGeheugenlimiet());
});

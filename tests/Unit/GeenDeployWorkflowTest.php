<?php

/**
 * Bewaakt dat geen workflow in deze branch nog naar een host deployt
 * (docs/plans/deploy-pipeline-uit-plan.md).
 *
 * De oude pipelines deployden met WireGuard en SSH naar de Raspberry Pi, die
 * gecompromitteerd is geweest. Productie wordt sinds 2026-10-12 met de hand
 * bijgewerkt. Komt er later een pipeline naar de nieuwe host, dan hoort die
 * deze test bewust aan te passen, in een eigen plan.
 */

/**
 * @return array<string, string>
 */
function workflowBestanden(): array
{
    $bestanden = glob(base_path('.github/workflows/*.{yml,yaml}'), GLOB_BRACE) ?: [];

    $inhoud = [];
    foreach ($bestanden as $pad) {
        $inhoud[basename($pad)] = (string) file_get_contents($pad);
    }

    return $inhoud;
}

it('vindt de workflows', function () {
    expect(workflowBestanden())->toHaveKey('ci.yml');
});

it('heeft geen deploy-workflow voor staging of productie', function () {
    expect(workflowBestanden())
        ->not->toHaveKey('deploy-staging.yml')
        ->not->toHaveKey('deploy-production.yml');
});

it('verwijst in geen workflow naar een deploy-environment of de deploymiddelen', function (string $patroon) {
    foreach (workflowBestanden() as $naam => $inhoud) {
        expect(preg_match($patroon, $inhoud))
            ->toBe(0, "{$naam} bevat {$patroon}");
    }
})->with([
    'environment production' => ['/^\s*environment:\s*[\'"]?production\b/m'],
    'environment staging' => ['/^\s*environment:\s*[\'"]?staging\b/m'],
    'remote_deploy.sh' => ['/remote_deploy\.sh/'],
    'wg-quick' => ['/wg-quick/'],
    'secrets.SSH_KEY' => ['/secrets\.SSH_KEY\b/'],
    'secrets.WG_CONFIG' => ['/secrets\.WG_CONFIG\b/'],
    'secrets.ENV_FILE_B64' => ['/secrets\.ENV_FILE_B64\b/'],
]);

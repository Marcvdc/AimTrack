<?php

/**
 * Het domein aimrack.nl bestaat niet, dus post naar security@aimrack.nl of
 * support@aimrack.nl bouncet. Deze test bewaakt de bestanden waarin een
 * contactadres naar buiten gaat. Historische stukken (changelog, afgeronde
 * plannen) staan bewust niet in de lijst: die beschrijven wat er toen gebeurd is.
 */
test('uitgaande contactadressen gebruiken een bestaand domein', function (string $relativePath) {
    $contents = file_get_contents(base_path($relativePath));

    expect($contents)
        ->not->toContain('aimrack.nl');
})->with([
    'README.md',
    'SECURITY.md',
    'CODE_OF_CONDUCT.md',
    '.env.example',
    '.env.local.example',
    'config/mail.php',
    'config/ai.php',
    'config/landing.php',
    'docs/infra.md',
]);

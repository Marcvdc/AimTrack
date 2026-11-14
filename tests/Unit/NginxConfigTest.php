<?php

/**
 * Bewaakt dat de nginx-container van AimTrack zijn versie niet meer meestuurt
 * (#183). Zonder server_tokens off staat het exacte versienummer in de
 * Server-header en op de foutpagina's van nginx.
 */
it('verbergt de nginx-versie in headers en foutpagina\'s', function () {
    $config = (string) file_get_contents(base_path('docker/nginx/default.conf'));

    expect($config)->toMatch('/^\s*server_tokens\s+off\s*;/m')
        ->not->toMatch('/^\s*server_tokens\s+(on|build)\b/m');
});

/**
 * Deelt de add_header-regels van een nginx-config in naar het blok waarin ze
 * staan. Een add_header in een location of if schakelt in nginx alle headers
 * van het server-niveau uit voor dat blok (#130), dus alles wat niet direct in
 * server staat telt als genest. Commentaar wordt overal buiten quotes
 * gestript, en puntkomma's binnen een waarde tussen dubbele quotes (de CSP)
 * breken een statement niet af.
 *
 * @return array{server: array<string, string>, genest: list<string>}
 */
function nginxAddHeaders(?string $config = null): array
{
    $config ??= (string) file_get_contents(base_path('docker/nginx/default.conf'));
    $config = (string) preg_replace_callback(
        '/"[^"]*"|#[^\n]*/',
        fn (array $match): string => str_starts_with($match[0], '"') ? $match[0] : '',
        $config,
    );

    preg_match_all('/(?:"[^"]*"|[^;{}"])*[;{}]/s', $config, $matches);

    $server = [];
    $genest = [];
    $stack = [];

    foreach ($matches[0] as $statement) {
        $statement = trim($statement);

        if (str_ends_with($statement, '{')) {
            $stack[] = (string) strtok($statement, " \t\n");

            continue;
        }

        if ($statement === '}') {
            array_pop($stack);

            continue;
        }

        if (! preg_match('/^add_header\s+(\S+)\s+(.*);$/s', $statement, $match)) {
            continue;
        }

        if ($stack === ['server']) {
            $server[strtolower($match[1])] = trim((string) preg_replace('/\s+/', ' ', $match[2]));

            continue;
        }

        $genest[] = $match[1];
    }

    return ['server' => $server, 'genest' => $genest];
}

it('zet de security headers op server-niveau met always', function (string $header, string $expected) {
    $headers = nginxAddHeaders()['server'];

    expect($headers)->toHaveKey($header);
    expect($headers[$header])
        ->toEndWith(' always')
        ->toContain($expected);
})->with([
    'nosniff' => ['x-content-type-options', 'nosniff'],
    'geen iframes' => ['x-frame-options', 'DENY'],
    'referrer' => ['referrer-policy', 'strict-origin-when-cross-origin'],
    'camera uit' => ['permissions-policy', 'camera=()'],
    'microfoon uit' => ['permissions-policy', 'microphone=()'],
    'geolocatie uit' => ['permissions-policy', 'geolocation=()'],
    'csp report-only' => ['content-security-policy-report-only', "default-src 'self'"],
]);

it('rolt de CSP alleen als report-only uit, met een startpolicy die Filament en Livewire niet breekt', function () {
    $headers = nginxAddHeaders()['server'];

    expect($headers)->not->toHaveKey('content-security-policy');

    expect($headers['content-security-policy-report-only'] ?? '')
        ->toContain("img-src 'self' data: blob: https://ui-avatars.com")
        ->toContain("style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net")
        ->toContain("font-src 'self' data: https://fonts.gstatic.com https://fonts.bunny.net")
        ->toContain("script-src 'self' 'unsafe-inline' 'unsafe-eval'")
        ->toContain("connect-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->not->toContain('report-uri');
});

it('laat geen location- of if-blok een eigen add_header zetten', function () {
    expect(nginxAddHeaders()['genest'])->toBe([]);
});

it('ziet add_header in geneste blokken en negeert commentaar achter een regel', function () {
    $config = <<<'NGINX'
        server {
            listen 80; # oud; add_header X-Commentaar "weg" always;
            server_name _; # was eerder: location /oud {
            add_header X-Server "a; b" always; # zie ook; add_header X-Ook-Weg "x";
            if ($request_method = POST) {
                add_header X-In-If "1";
            }
            location / {
                if ($arg_x) { add_header X-In-Location-If "1"; }
                add_header X-In-Location "1";
            }
        }
        NGINX;

    $headers = nginxAddHeaders($config);

    expect($headers['server'])->toBe(['x-server' => '"a; b" always'])
        ->and($headers['genest'])->toBe(['X-In-If', 'X-In-Location-If', 'X-In-Location']);
});

it('zet geen HSTS op de origin, die hoort bij de TLS-proxy', function () {
    expect(nginxAddHeaders()['server'])->not->toHaveKey('strict-transport-security');
});

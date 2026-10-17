<?php

/**
 * Bewaakt dat de nginx-container van AimTrack zijn versie niet meer meestuurt
 * (#183). Zonder server_tokens off staat het exacte versienummer in de
 * Server-header en op de foutpagina's van nginx.
 */
it('verbergt de nginx-versie in headers en foutpagina\'s', function () {
    $config = (string) file_get_contents(base_path('docker/nginx/default.conf'));

    expect($config)->toMatch('/^\s*server_tokens\s+off\s*;/m');
});

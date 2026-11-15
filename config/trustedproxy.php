<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vertrouwde proxies
    |--------------------------------------------------------------------------
    |
    | Illuminate\Http\Middleware\TrustProxies leest deze waarde zelf uit op het
    | moment dat een request binnenkomt. Dat is bewust: de middleware-callback in
    | bootstrap/app.php draait vóórdat de configuratie geladen is, dus daar is
    | config() nog niet beschikbaar.
    |
    | Zonder een waarde vertrouwt de applicatie géén enkele proxy. Alles komt dan
    | binnen als afkomstig van de TLS-proxy: request()->ip() geeft het proxy-adres
    | terug, alle IP-gebaseerde rate limiting valt samen in één bucket, en logs
    | wijzen niet naar de werkelijke bron.
    |
    | Vul hier het adres of bereik van de eigen TLS-proxy in, bijvoorbeeld
    | TRUSTED_PROXIES=172.18.0.0/16 voor het Docker-netwerk.
    |
    | Zet dit NIET op '*' zolang de origin ook buiten de proxy om bereikbaar is
    | (zie issue #123). Met '*' vertrouwt Laravel de aanroepende partij, en kan
    | iemand die de origin rechtstreeks bereikt zijn eigen X-Forwarded-For zetten
    | en daarmee zowel de rate limiting als de logging om de tuin leiden.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];

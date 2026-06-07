<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Landingspagina — instance-configuratie
    |--------------------------------------------------------------------------
    |
    | De publieke landingspagina (route `welcome`) toont instance-brede
    | aggregaten. `club` bepaalt de clubnaam in de trust-strip + footer en is
    | per-instance overschrijfbaar via de LANDING_CLUB env-variabele.
    | `partner_clubs` is een optionele lijst van extra clubnamen.
    */

    'club' => env('LANDING_CLUB', 'SSV Scherpschutters'),

    'partner_clubs' => [],

    // Cache-TTL (seconden) voor de aggregaat-statistieken op de landingspagina.
    'stats_cache_ttl' => 3600,

    /*
    |--------------------------------------------------------------------------
    | KNSA-kennisbank-links
    |--------------------------------------------------------------------------
    |
    | Niet gebruikt op de marketing-landingspagina, maar wél door het Filament
    | dashboard (App\Filament\Pages\Dashboard::getKnsaLinks()). Bewust hier
    | behouden als gedeelde bron.
    */
    'knsa_links' => [
        [
            'title' => 'Schiet- en Wedstrijdreglementen',
            'description' => 'Volledige set reglementen en procedures voor baanwedstrijden en competities.',
            'url' => 'https://www.knsa.nl/de-knsa/wet-en-regelgeving/schiet-en-wedstrijdreglementen/',
        ],
        [
            'title' => 'KNSA Downloadcenter',
            'description' => 'Alle actuele formulieren, protocollen en veiligheidsdocumenten overzichtelijk gebundeld.',
            'url' => 'https://www.knsa.nl/downloadcenter/',
        ],
        [
            'title' => 'Wet- en Regelgeving algemeen',
            'description' => 'Overzichtspagina met uitleg over regelgeving, vergunningen en toezicht binnen de schietsport.',
            'url' => 'https://www.knsa.nl/de-knsa/wet-en-regelgeving/algemeen/',
        ],
    ],
];

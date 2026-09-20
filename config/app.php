<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    'force_https' => (bool) env('APP_FORCE_HTTPS', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | De OPSLAG-tijdzone. Alles in de database staat in UTC en Laravel
    | schrijft en leest datetime-kolommen in deze tijdzone. Zet dit dus
    | niet zomaar op Europe/Amsterdam: de timestamp-kolommen dragen geen
    | zone mee, dus bestaande rijen zouden stil een of twee uur opschuiven
    | zonder dat er een byte in de database verandert. Voor wat de
    | gebruiker ziet is 'display_timezone' hieronder de knop.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Application Display Timezone
    |--------------------------------------------------------------------------
    |
    | De WEERGAVE-tijdzone. Hierin toont de app tijden aan de gebruiker,
    | terwijl de opslag in UTC blijft. Gebruik App\Support\DateFormat of
    | de Filament-datetime-helpers om te converteren; die volgen deze
    | instelling via FilamentTimezone.
    |
    */

    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'Europe/Amsterdam'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | AimTrack is een Nederlandstalige applicatie, dus 'nl' is de
    | code-default en niet iets dat per omgeving gezet moet worden. De
    | Nederlandse vertalingen staan in lang/nl (Laravel zelf levert alleen
    | 'en' mee); Filament brengt zijn eigen nl-bestanden mee in vendor.
    | De fallback blijft 'en' zodat een ontbrekende sleutel een leesbare
    | Engelse zin geeft in plaats van de kale sleutelnaam.
    |
    */

    'locale' => env('APP_LOCALE', 'nl'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'nl_NL'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];

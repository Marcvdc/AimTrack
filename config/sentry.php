<?php

use App\Support\Sentry\ScrubRequestData;

return [
    'dsn' => env('SENTRY_LARAVEL_DSN'),

    'release' => env('SENTRY_RELEASE'),

    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),

    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.0),

    'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.0),

    'send_default_pii' => false,

    'max_request_body_size' => 'never',

    'before_send' => [ScrubRequestData::class, 'beforeSend'],

    'attach_stacktrace' => true,
];

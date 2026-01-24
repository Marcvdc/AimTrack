<?php

return [
    'defaults' => [
        'aimtrack-ai' => (bool) env('FEATURE_AIMTRACK_AI', env('APP_ENV') === 'local'),
    ],
];

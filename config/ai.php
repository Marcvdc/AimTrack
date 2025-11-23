<?php

return [
    'driver' => env('AI_DRIVER', 'openai'),
    'model' => env('AI_MODEL', 'gpt-4.1-mini'),
    'base_url' => env('OPENAI_BASE_URL'),
    'api_key' => env('OPENAI_API_KEY'),
];

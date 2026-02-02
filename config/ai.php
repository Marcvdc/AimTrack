<?php

return [
    'driver' => env('AI_DRIVER', 'openai'),
    'model' => env('AI_MODEL', 'gpt-4.1-mini'),
    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    'api_key' => env('OPENAI_API_KEY'),
    'daily_question_limit' => (int) env('AI_DAILY_QUESTION_LIMIT', 10),
    'daily_question_decay_minutes' => (int) env('AI_DAILY_QUESTION_DECAY_MINUTES', 1_440),
    'alert_email' => env('AI_ALERT_EMAIL', 'support@aimtrack.nl'),
    'alert_cooldown_minutes' => (int) env('AI_ALERT_COOLDOWN_MINUTES', 15),
    'alert_failure_threshold' => (int) env('AI_ALERT_FAILURE_THRESHOLD', 3),
];

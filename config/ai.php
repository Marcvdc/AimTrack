<?php

return [
    'driver' => env('AI_DRIVER', 'anthropic'), // informatief; ShooterCoach praat raw met de Anthropic Messages API
    'model' => env('AI_MODEL', 'claude-haiku-4-5-20251001'),
    'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 1024),
    'anthropic_version' => env('ANTHROPIC_VERSION', '2023-06-01'),
    // 'api_key' (OPENAI_API_KEY) is deprecated na de migratie naar Claude; de key is nu per-user via AiKeyResolver.
    'daily_question_limit' => (int) env('AI_DAILY_QUESTION_LIMIT', 10),
    'daily_question_decay_minutes' => (int) env('AI_DAILY_QUESTION_DECAY_MINUTES', 1_440),
    'alert_email' => env('AI_ALERT_EMAIL', 'support@aimtrack.nl'),
    'alert_cooldown_minutes' => (int) env('AI_ALERT_COOLDOWN_MINUTES', 15),
    'alert_failure_threshold' => (int) env('AI_ALERT_FAILURE_THRESHOLD', 3),
];

<?php

return [
    'mock_mode' => env('AI_MOCK_MODE', true),

    'timeouts' => [
        'request_seconds' => (int) env('AI_REQUEST_TIMEOUT', 30),
    ],

    'prompt_versions' => [
        'summarize' => env('AI_PROMPT_VERSION_SUMMARIZE', 'v1.0.0'),
        'extract' => env('AI_PROMPT_VERSION_EXTRACT', 'v1.0.0'),
        'classify' => env('AI_PROMPT_VERSION_CLASSIFY', 'v1.0.0'),
    ],

    'providers' => [
        'openai' => [
            'default_model' => env('AI_OPENAI_MODEL', 'gpt-4.1-mini'),
        ],
        'gemini' => [
            'default_model' => env('AI_GEMINI_MODEL', 'gemini-2.0-flash'),
        ],
    ],

    'resilience' => [
        'circuit_breaker' => [
            'failure_threshold' => (int) env('AI_CIRCUIT_FAILURE_THRESHOLD', 5),
            'cooldown_minutes' => (int) env('AI_CIRCUIT_COOLDOWN_MINUTES', 15),
        ],
    ],

    'limits' => [
        'actions_per_hour' => (int) env('AI_ACTIONS_PER_HOUR', 30),
    ],
];

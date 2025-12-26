<?php

return [
    'base_url' => env('LITELLM_BASE_URL', 'http://localhost:4000'),
    'api_key' => env('LITELLM_API_KEY', null),

    'timeout' => env('LITELLM_TIMEOUT', 120),
    'connect_timeout' => env('LITELLM_CONNECT_TIMEOUT', 10),

    'max_retries' => env('LITELLM_MAX_RETRIES', 2),
    'retry_delay_ms' => env('LITELLM_RETRY_DELAY_MS', 500),

    'models' => [
        'fast' => [
            'anthropic/claude-haiku-4-5',
        ],
        'deep' => [
            'anthropic/claude-sonnet-4-5',
        ],
    ],

    'routing' => [
        'large_request_threshold' => env('LARGE_REQUEST_THRESHOLD', 8000),
        'quality_header' => 'x-quality',
    ],

    'cache' => [
        'enabled' => env('LITELLM_CACHE_ENABLED', true),
        'ttl_seconds' => env('LITELLM_CACHE_TTL', 86400),
        'store' => env('CACHE_DRIVER', 'redis'),
    ],

    'logging' => [
        'log_prompts' => env('LOG_PROMPTS', false),
        'log_responses' => env('LOG_RESPONSES', false),
    ],

    'decomposition' => [
        'enabled' => env('DECOMPOSITION_ENABLED', true),
        'max_internal_calls' => 4,
        'max_chunks' => 3,
    ],
];

